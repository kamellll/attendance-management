<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Rest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AttendanceUpdateRequest;
class AttendanceController extends Controller
{
    public function index()
    {
        $now = Carbon::now(); // Asia/Tokyoなら通常これでOK

        // 日本語の曜日
        $jpWeek = ['日', '月', '火', '水', '木', '金', '土'];
        $week = $jpWeek[$now->dayOfWeek]; // 0=日, 6=土

        // 例: 2026年1月18日（日）
        $dateWithWeek = $now->format('Y年n月j日') . "({$week})";

        $userId = auth()->id();           // ログインユーザー
        $today = Carbon::today();        // 今日(00:00:00)

        // 今日の go があるレコードを1件取得（最新が欲しいなら orderByDesc を付ける）
        $todayStatus = Status::query()
            ->where('user_id', $userId)
            ->whereDate('go', $today)     // go の日付部分が今日
            ->orderByDesc('go')
            ->first();

        // ①②：なければ 0、あれば status を代入
        $status = $todayStatus ? $todayStatus->status : 0;
        $statusLavel = ['勤務外', '出勤中', '休憩中', '退勤済',];
        $statusStr = $statusLavel[$status];

        // 例: 08:00
        $time = $now->format('H:i');
        return view('attendance', compact('status', 'statusStr', 'dateWithWeek', 'time'));
    }
    public function store(Request $request)
    {
        $userId = auth()->id();
        $now = Carbon::now();

        // 今日の出勤レコード（goの日付が今日）が既にあるか確認
        $today = Carbon::today();

        $already = Status::query()
            ->where('user_id', $userId)
            ->whereDate('go', $today)
            ->exists();

        if ($already) {
            return redirect('/attendance')->with('message', '本日は既に出勤済みです。');
        }

        // 作成（nullableのカラムは入れなくてOK）
        Status::create([
            'user_id' => $userId,
            'status' => 1,    // 出勤したら1（あなたの状態定義に合わせて変更OK）
            'go' => $now,
            'apply' => 0,
            // rest/back/sum/applied_at は nullable なので省略
        ]);

        return redirect('/attendance')->with('message', '出勤しました。');
    }
    public function rest(Request $request)
    {
        $userId = auth()->id();
        $now = Carbon::now();

        DB::transaction(function () use ($userId, $now) {

            // 今日の出勤レコードを取得
            $statusRow = Status::query()
                ->where('user_id', $userId)
                ->whereDate('go', Carbon::today())
                ->orderByDesc('go')
                ->lockForUpdate()
                ->first();

            if (!$statusRow) {
                abort(400, '本日の出勤レコードが見つかりません。');
            }

            // status を 2（休憩中）に変更
            $statusRow->update([
                'status' => 2,
            ]);

            // rests に1件作成（休憩開始）
            Rest::create([
                'statuses_id' => $statusRow->id,
                'start' => $now,
                'end' => null, // end は休憩戻りで入れる想定
            ]);
        });

        return redirect('/attendance')->with('message', '休憩に入りました。');
    }
    public function backRest()
    {
        $userId = auth()->id();
        $now = Carbon::now();

        DB::transaction(function () use ($userId, $now) {

            // 今日の出勤レコード
            $statusRow = Status::query()
                ->where('user_id', $userId)
                ->whereDate('go', Carbon::today())
                ->orderByDesc('go')
                ->lockForUpdate()
                ->first();

            if (!$statusRow) {
                abort(400, '本日の出勤レコードが見つかりません。');
            }

            // end が NULL の休憩（＝休憩中）を1件だけ閉じる
            $restRow = Rest::query()
                ->where('statuses_id', $statusRow->id)
                ->whereNull('end')
                ->orderByDesc('start')
                ->lockForUpdate()
                ->first();

            if (!$restRow) {
                abort(400, '休憩中のレコードが見つかりません。');
            }

            // 休憩終了時刻を入れる
            $restRow->update(['end' => $now]);

            // 休憩秒を加算（statusesに合計を持つ想定）
            $addSeconds = $restRow->start->diffInSeconds($now);
            $statusRow->update([
                'status' => 1, // 例：勤務中に戻す（あなたの状態定義に合わせて）
                'rest' => $statusRow->rest + $addSeconds,
            ]);
        });

        return redirect('/attendance')->with('message', '休憩から戻りました。');
    }

    public function back()
    {
        $userId = auth()->id();
        $now = Carbon::now();

        DB::transaction(function () use ($userId, $now) {

            $statusRow = Status::query()
                ->where('user_id', $userId)
                ->whereDate('go', Carbon::today())
                ->orderByDesc('go')
                ->lockForUpdate()
                ->first();

            if (!$statusRow) {
                abort(400, '本日の出勤レコードが見つかりません。');
            }

            // 退勤前に「休憩中(endがNULL)」があれば閉じて休憩合計に加算
            $openRest = Rest::query()
                ->where('statuses_id', $statusRow->id)
                ->whereNull('end')
                ->orderByDesc('start')
                ->lockForUpdate()
                ->first();

            if ($openRest) {
                $openRest->end = $now;
                $openRest->save();

                $addSeconds = Carbon::parse($openRest->start)->diffInSeconds($now);
                $statusRow->rest_total_seconds = (int) $statusRow->rest_total_seconds + (int) $addSeconds;
                $statusRow->save(); // いったん反映
            }

            // 勤務時間（秒）= 退勤 - 出勤 - 休憩合計
            $go = Carbon::parse($statusRow->go);
            $workSeconds = $go->diffInSeconds($now) - (int) $statusRow->rest_total_seconds;
            if ($workSeconds < 0)
                $workSeconds = 0;

            // 退勤処理：back に退勤時刻、status=3、sum に勤務合計秒
            $statusRow->update([
                'status' => 3,
                'back' => $now,         // 退勤時刻
                'sum' => $workSeconds, // 勤務合計(秒)
            ]);
        });

        return redirect('/attendance')->with('message', '退勤しました。');
    }
    private const SESSION_KEY = 'attendance.list.month'; // 'Y-m' を保存

    public function list(Request $request)
    {
        $userId = auth()->id();

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

        return view('attendance-list', compact(
            'monthLabel',
            'items',
            'prevYm',
            'nextYm'
        ));
    }

    public function moveMonth(Request $request)
    {
        $userId = auth()->id();
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

        return redirect()->route('attendance.list');
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
            ->where('user_id', $userId) // 他人のを見せない
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

        return view('attendance-detail', [
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
        ]);
    }

    public function update(AttendanceUpdateRequest $request)
    {
        $validated = $request->validated(); // Laravel8: 引数なしで配列が返る

        $userId = auth()->id();
        $statusId = (int) $validated['statuses_id'];

        DB::transaction(function () use ($validated, $userId, $statusId) {

            $status = Status::query()
                ->where('id', $statusId)
                ->where('user_id', $userId)
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

        return redirect()->route('attendance.list')->with('message', '更新しました。');
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
        $isAdmin = (int) auth()->user()->admin === 1;

        $q = Status::query()
            ->with(['user:id,name'])
            ->where('apply', $apply);

        if (!$isAdmin) {
            $q->where('user_id', auth()->id()); // 一般は自分の申請のみ
        }

        $items = $q->orderByDesc('applied_at')->paginate(20);

        return view('stamp-correction', [
            'items' => $items,
            'applyFilter' => $apply,
            'isAdmin' => $isAdmin,
        ]);
    }
}
