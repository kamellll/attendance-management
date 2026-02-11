<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Status;
use App\Models\Rest;
use App\Models\User;
use App\Http\Requests\AttendanceUpdateRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
class AdminController extends Controller
{
    public function index()
    {
        return view('/admin/login');
    }
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        // remember me も使うなら第2引数に渡す
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {

            // ここで「未登録 or 不一致」のエラーメッセージを付けて元の画面に戻す
            return back()->withErrors([
                'email' => 'ログイン情報が登録されていません',
            ])->onlyInput('email'); // email だけ old() で保持
        }

        // ログイン成功時はセッション再生成
        $request->session()->regenerate();

        return redirect('/admin/attendance/list');
    }

    private const SESSION_KEY = 'attendance.list.month';
    public function list(Request $request)
    {
        $userId = auth()->id();

        Carbon::setLocale('ja');

        // このユーザーが持つ「データがある日」一覧（降順）
        $dayKeys = Status::query()
            ->selectRaw("DATE_FORMAT(go, '%Y-%m-%d') as ymd")
            ->groupBy('ymd')
            ->orderByDesc('ymd')
            ->pluck('ymd')
            ->values();

        // セッションに保存されている表示日（なければ最新日 or 今日）
        $ymd = session(self::SESSION_KEY);

        if (!$ymd) {
            $ymd = $dayKeys->first() ?? Carbon::now()->format('Y-m-d');
            session([self::SESSION_KEY => $ymd]);
        }

        // 日が存在しない（削除などで）場合は最新に寄せる
        if ($dayKeys->count() > 0 && !$dayKeys->contains($ymd)) {
            $ymd = $dayKeys->first();
            session([self::SESSION_KEY => $ymd]);
        }

        $day = Carbon::createFromFormat('Y-m-d', $ymd)->startOfDay();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        // 表示日の勤怠（通常は1件想定だが、複数あっても一覧で表示できるように）
        $items = Status::query()
            ->whereBetween('go', [$start, $end])
            ->orderBy('go', 'asc')
            ->get();

        // 表示ラベル：日付（例：2026/01/26(月)）
        $dayLabel = $day->format('Y/m/d');

        // 各レコードの日付ラベル：mm/dd(月) など（必要なら）
        $items->transform(function ($row) {
            $d = Carbon::parse($row->go);
            $row->date_label = $d->format('m/d') . '(' . $d->isoFormat('ddd') . ')';
            return $row;
        });

        // 前日・翌日（データがある日だけリンクを出す）
        $idx = $dayKeys->search($ymd); // 0-based（降順）
        $prevYmd = null; // 古い日（前日ボタン）
        $nextYmd = null; // 新しい日（翌日ボタン）

        if ($idx !== false) {
            $prevYmd = $dayKeys->get($idx + 1); // より古い日
            $nextYmd = $idx > 0 ? $dayKeys->get($idx - 1) : null; // より新しい日
        }

        return view('/admin/attendance-list', compact(
            'day',
            'items',
            'prevYmd',
            'nextYmd'
        ));
    }


    public function moveDay(Request $request)
    {
        $direction = $request->input('direction'); // 'prev' or 'next'

        if (!in_array($direction, ['prev', 'next'], true)) {
            return redirect()->route('attendance.list');
        }

        // データがある日一覧（降順）
        $dayKeys = Status::query()
            ->selectRaw("DATE_FORMAT(go, '%Y-%m-%d') as ymd")
            ->groupBy('ymd')
            ->orderByDesc('ymd')
            ->pluck('ymd')
            ->values();

        // データが何もないならそのまま戻す
        if ($dayKeys->isEmpty()) {
            session([self::SESSION_KEY => Carbon::now()->format('Y-m-d')]);
            return redirect()->route('attendance.list');
        }

        $currentYmd = session(self::SESSION_KEY) ?? $dayKeys->first();

        // もしセッションの日付が存在しない（削除など）場合は最新日に寄せる
        if (!$dayKeys->contains($currentYmd)) {
            $currentYmd = $dayKeys->first();
        }

        $idx = $dayKeys->search($currentYmd);
        if ($idx === false) {
            $idx = 0;
        }

        // 移動先（日が存在する時だけ移動）
        if ($direction === 'prev') {
            $target = $dayKeys->get($idx + 1); // 古い日へ
        } else {
            $target = $idx > 0 ? $dayKeys->get($idx - 1) : null; // 新しい日へ
        }

        if ($target) {
            session([self::SESSION_KEY => $target]);
        }

        return redirect('/admin/attendance/list');
    }

    public function detail(int $id)
    {
        Carbon::setLocale('ja');

        $userId = auth()->id();

        $status = Status::query()
            ->with([
                'user:id,name',
                'rests' => fn($q) => $q->orderBy('start', 'asc'),
            ])
            ->where('id', $id)
            ->firstOrFail();

        // ここから表示用整形（前回と同じ）
        $go = Carbon::parse($status->go);
        $back = $status->back ? Carbon::parse($status->back) : null;

        $nameLabel = $status->user->name;
        $yearLabel = $go->format('Y年');
        $dateLabel = $go->format('n月j日');
        $goTime = $go->format('H:i');
        $backTime = $back ? $back->format('H:i') : '-';

        $rests = $status->rests->map(function ($r) {
            return [
                'id' => $r->id,
                'start' => Carbon::parse($r->start)->format('H:i'),
                'end' => $r->end ? Carbon::parse($r->end)->format('H:i') : '-',
            ];
        });

        return view('/admin/attendance-detail', [
            'userId' => $userId,
            'id' => $status->id,
            'name' => $nameLabel,
            'yearLabel' => $yearLabel,
            'dateLabel' => $dateLabel,
            'goTime' => $goTime,
            'backTime' => $backTime,
            'rests' => $rests,
            'note' => $status->note ?? '',
            'apply' => $status->apply,
            'restAdd' => $status->rest_add,
        ]);
    }

    public function update(AttendanceUpdateRequest $request)
    {
        $validated = $request->validated(); // Laravel8: 引数なしで配列が返る

        $userId = $request->input('userId');
        $statusId = (int) $validated['statuses_id'];

        DB::transaction(function () use ($validated, $userId, $statusId) {

            $status = Status::query()
                ->where('id', $statusId)
                ->lockForUpdate()
                ->firstOrFail();

            // 基準日（勤怠日）
            $baseDate = Carbon::parse($status->go)->startOfDay();

            // ★ここが今回のエラー対策：validated('go') は使わず配列から取得
            $goAt = $baseDate->copy()->setTimeFromTimeString($validated['go']);   // string "HH:MM"
            $backAt = $baseDate->copy()->setTimeFromTimeString($validated['back']); // string "HH:MM"

            // 既存休憩の更新
            $restIds = $validated['rest_ids'] ?? [];
            $starts = $validated['start'] ?? [];
            $ends = $validated['end'] ?? [];

            for ($i = 0; $i < count($restIds); $i++) {
                $rid = (int) $restIds[$i];

                $restRow = Rest::query()
                    ->where('id', $rid)
                    ->where('statuses_id', $status->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $startStr = trim((string) ($starts[$i] ?? ''));
                $endStr = trim((string) ($ends[$i] ?? ''));

                // startが空の行は更新しない（仕様）
                if ($startStr === '') {
                    continue;
                }

                $startAt = $baseDate->copy()->setTimeFromTimeString($startStr);
                $endAt = ($endStr !== '') ? $baseDate->copy()->setTimeFromTimeString($endStr) : null;

                $restRow->update([
                    'start' => $startAt,
                    'end' => $endAt,
                ]);
            }

            // 追加用の休憩（new_start が入っているときだけ作成）
            $newStart = trim((string) ($validated['new_start'] ?? ''));
            $newEnd = trim((string) ($validated['new_end'] ?? ''));

            //休憩が追加されたかどうか0: 未追加、 1:追加
            $restAdd = 0;
            if ($newStart !== '') {
                $restAdd = 1;
                $newStartAt = $baseDate->copy()->setTimeFromTimeString($newStart);
                $newEndAt = ($newEnd !== '') ? $baseDate->copy()->setTimeFromTimeString($newEnd) : null;

                // ※ Restモデルで create を使うなら fillable が必要
                Rest::create([
                    'statuses_id' => $status->id,
                    'start' => $newStartAt,
                    'end' => $newEndAt,
                ]);
            }

            // DBの休憩を再取得して合計秒を再計算（安全＆確実）
            $rests = Rest::query()
                ->where('statuses_id', $status->id)
                ->lockForUpdate()
                ->get();

            $restTotalSeconds = 0;
            foreach ($rests as $r) {
                if ($r->end) {
                    $restTotalSeconds += Carbon::parse($r->start)->diffInSeconds(Carbon::parse($r->end));
                }
            }

            // 勤務合計秒 = (退勤-出勤) - 休憩秒
            $workSeconds = $goAt->diffInSeconds($backAt) - $restTotalSeconds;
            if ($workSeconds < 0)
                $workSeconds = 0;

            // statuses 更新（秒で統一）
            $status->update([
                'go' => $goAt,
                'back' => $backAt,
                'rest' => $restTotalSeconds,
                'sum' => $workSeconds,
                'note' => $validated['note'],
                'apply' => 1,
                'applied_at' => now(),
                'rest_add' => $restAdd,
            ]);
        });

        return redirect('/admin/attendance/list');
    }

    public function stampCorrectionIndex(Request $request)
    {
        // 初回表示：apply=1
        return $this->renderStampCorrectionList(1);
    }

    public function stampCorrectionFilter(Request $request)
    {
        // 押されたボタンで切り替え
        // wait が押された → apply=1
        // applied が押された → apply=2
        // どちらも無い → apply=1
        $apply = $request->has('applied') ? 2 : 1;

        return $this->renderStampCorrectionList($apply);
    }

    private function renderStampCorrectionList(int $apply)
    {
        $userId = auth()->id();

        $items = Status::query()
            ->with(['user:id,name'])
            ->where('apply', $apply)
            ->orderByDesc('applied_at')
            ->paginate(20);
        dd(
            Status::where('apply', 1)->count(),
            Status::where('apply', 2)->count()
        );
        return view('admin.stamp-correction', [
            'items' => $items,
            'applyFilter' => $apply, // どっち表示中か（表示切替に使える）
        ]);
    }

    public function approve(int $id)
    {
        Carbon::setLocale('ja');

        $status = Status::query()
            ->with([
                'user:id,name',
                'rests' => fn($q) => $q->orderBy('start', 'asc'),
            ])
            ->where('id', $id)
            ->firstOrFail();

        // ここから表示用整形（前回と同じ）
        $go = Carbon::parse($status->go);
        $back = $status->back ? Carbon::parse($status->back) : null;

        $nameLabel = $status->user->name;
        $yearLabel = $go->format('Y年');
        $dateLabel = $go->format('n月j日');
        $goTime = $go->format('H:i');
        $backTime = $back ? $back->format('H:i') : '-';

        $rests = $status->rests->map(function ($r) {
            return [
                'id' => $r->id,
                'start' => Carbon::parse($r->start)->format('H:i'),
                'end' => $r->end ? Carbon::parse($r->end)->format('H:i') : '-',
            ];
        });

        return view('/admin/approve', [
            'id' => $status->id,
            'name' => $nameLabel,
            'yearLabel' => $yearLabel,
            'dateLabel' => $dateLabel,
            'goTime' => $goTime,
            'backTime' => $backTime,
            'rests' => $rests,
            'note' => $status->note ?? '',
            'apply' => $status->apply,
            'restAdd' => $status->rest_add,
        ]);
    }

    public function apply(Request $request)
    {

        $statusId = (int) $request->input('statuses_id');

        $status = Status::query()
            ->where('id', $statusId)
            ->lockForUpdate()
            ->firstOrFail();

        // statuses 更新（秒で統一）
        $status->update([
            'apply' => 2,
            'applied_at' => now(),
        ]);

        return redirect('/admin/attendance/list');
    }

    public function staffList(Request $request)
    {
        $userId = auth()->id();

        // 表示日の勤怠（通常は1件想定だが、複数あっても一覧で表示できるように）
        $users = User::orderByDesc('id')->get();

        return view('/admin/staff-list', compact(
            'users',
        ));
    }

    public function staff($id)
    {
        $userId = $id;

        Carbon::setLocale('ja');

        // このユーザーが持つ「データがある月」一覧（降順）
        $monthKeys = Status::query()
            ->where('user_id', $userId)
            ->selectRaw("DATE_FORMAT(go, '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->pluck('ym')
            ->values();

        // セッションに保存されている表示月（なければ最新月 or 今月）
        $ym = session(self::SESSION_KEY);

        if (!$ym) {
            $ym = $monthKeys->first() ?? Carbon::now()->format('Y-m');
            session([self::SESSION_KEY => $ym]);
        }

        // 月が存在しない（削除などで）場合は最新に寄せる
        if ($monthKeys->count() > 0 && !$monthKeys->contains($ym)) {
            $ym = $monthKeys->first();
            session([self::SESSION_KEY => $ym]);
        }

        $month = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // 表示月の勤怠
        $items = Status::query()
            ->where('user_id', $userId)
            ->whereBetween('go', [$start, $end])
            ->orderBy('go', 'asc')
            ->get();

        // 表示ラベル：年月（2026/01）
        $monthLabel = $month->format('Y/m');

        // 日付ラベル：2026/01/26(月)
        $items->transform(function ($row) {
            $d = Carbon::parse($row->go);
            $row->date_label = $d->format('m/d') . '(' . $d->isoFormat('ddd') . ')';
            return $row;
        });

        // 前月・翌月（データがある月だけリンクを出す）
        $idx = $monthKeys->search($ym); // 0-based（降順）
        $prevYm = null; // 古い月（前月ボタン）
        $nextYm = null; // 新しい月（翌月ボタン）

        if ($idx !== false) {
            $prevYm = $monthKeys->get($idx + 1);        // 例：2025-11（より古い）
            $nextYm = $idx > 0 ? $monthKeys->get($idx - 1) : null; // 例：2026-01（より新しい）
        }

        return view('/admin/attendance-staff', compact(
            'userId',
            'monthLabel',
            'items',
            'prevYm',
            'nextYm'
        ));
    }

    public function moveMonth(Request $request)
    {
        $userId = $request->userId;
        $direction = $request->input('direction'); // 'prev' or 'next'

        if (!in_array($direction, ['prev', 'next'], true)) {
            return redirect()->route('attendance.list');
        }

        // データがある月一覧（降順）
        $monthKeys = Status::query()
            ->where('user_id', $userId)
            ->selectRaw("DATE_FORMAT(go, '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->pluck('ym')
            ->values();

        // データが何もないならそのまま戻す
        if ($monthKeys->isEmpty()) {
            session([self::SESSION_KEY => Carbon::now()->format('Y-m')]);
            return redirect()->route('attendance.list');
        }

        $currentYm = session(self::SESSION_KEY) ?? $monthKeys->first();
        if (!$monthKeys->contains($currentYm)) {
            $currentYm = $monthKeys->first();
        }

        $idx = $monthKeys->search($currentYm);
        if ($idx === false) {
            $idx = 0;
        }

        // 移動先（月が存在する時だけ移動）
        if ($direction === 'prev') {
            $target = $monthKeys->get($idx + 1); // 古い方へ
        } else {
            $target = $idx > 0 ? $monthKeys->get($idx - 1) : null; // 新しい方へ
        }

        if ($target) {
            session([self::SESSION_KEY => $target]);
        }

        return redirect()->route('admin.attendance.staff');
    }

    public function staffCsv($id): StreamedResponse
    {
        $userId = (int) $id;

        Carbon::setLocale('ja');

        // 表示対象ユーザー名（ファイル名に使う用）
        $user = User::query()->select('id', 'name')->findOrFail($userId);

        // このユーザーが持つ「データがある月」一覧（降順）
        $monthKeys = Status::query()
            ->where('user_id', $userId)
            ->selectRaw("DATE_FORMAT(go, '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->pluck('ym')
            ->values();

        // データが無ければ空CSVを返す（または戻すでもOK）
        $ym = session(self::SESSION_KEY);
        if (!$ym) {
            $ym = $monthKeys->first() ?? Carbon::now()->format('Y-m');
        }
        if ($monthKeys->count() > 0 && !$monthKeys->contains($ym)) {
            $ym = $monthKeys->first();
        }

        $month = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $items = Status::query()
            ->where('user_id', $userId)
            ->whereBetween('go', [$start, $end])
            ->orderBy('go', 'asc')
            ->get();

        $fileName = sprintf(
            'attendance_%s_%s.csv',
            $user->name,
            $month->format('Y-m')
        );

        // Excel文字化け対策：BOM付きUTF-8
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');

            // BOM
            fwrite($out, "\xEF\xBB\xBF");

            // ヘッダー行
            fputcsv($out, ['日付', '出勤', '退勤', '休憩合計', '合計勤務時間']);

            foreach ($items as $row) {
                $go = $row->go ? Carbon::parse($row->go) : null;
                $back = $row->back ? Carbon::parse($row->back) : null;

                $dateLabel = $go
                    ? $go->format('Y/m/d') . '(' . $go->isoFormat('ddd') . ')'
                    : '';

                $goTime = $go ? $go->format('H:i') : '';
                $backTime = $back ? $back->format('H:i') : '';

                // rest/sum は秒なので HH:MM に
                $restLabel = gmdate('H:i', (int) $row->rest);
                $sumLabel = gmdate('H:i', (int) $row->sum);

                fputcsv($out, [$dateLabel, $goTime, $backTime, $restLabel, $sumLabel]);
            }

            fclose($out);
        }, $fileName, $headers);
    }
}
