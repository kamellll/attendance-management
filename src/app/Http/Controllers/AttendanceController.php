<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Rest;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public function list(Request $request)
    {
        $userId = auth()->id();

        $monthParam = $request->query('month');

        if ($monthParam) {
            $month = Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth();
        } else {
            $latestGo = Status::where('user_id', $userId)->max('go');
            $month = $latestGo
                ? Carbon::parse($latestGo)->startOfMonth()
                : Carbon::now()->startOfMonth();
        }

        // ★ 日本語曜日を出すため
        Carbon::setLocale('ja');

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $items = Status::query()
            ->where('user_id', $userId)
            ->whereBetween('go', [$start, $end])
            ->orderBy('go', 'asc')
            ->get();

        // ===== 表示フォーマット用 =====
        // 表示中の年月：2026/01
        $monthLabel = $month->format('Y/m');

        // 各レコードの日付：2026/01/26(月)
        // isoFormat('ddd') が「月」などの日本語曜日になります（locale=ja前提）
        $items->transform(function ($row) {
            $row->date_label = Carbon::parse($row->go)->format('Y/m/d')
                . '(' . Carbon::parse($row->go)->isoFormat('ddd') . ')';
            return $row;
        });

        // ===== 月ごとページネーション（1ページ=1か月）=====
        $monthKeys = Status::query()
            ->where('user_id', $userId)
            ->selectRaw("DATE_FORMAT(go, '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->pluck('ym')
            ->values();

        $currentPage = 1;
        if ($monthKeys->count() > 0) {
            $idx = $monthKeys->search($month->format('Y-m'));
            $currentPage = ($idx === false) ? 1 : ($idx + 1);
        }

        $paginator = new LengthAwarePaginator(
            $items,
            $monthKeys->count(),
            1,
            $currentPage,
            [
                'path' => url('/attendance/list'),
                'query' => ['month' => $month->format('Y-m')],
            ]
        );

        // 前/次月（クエリ用）
        $prevYm = $monthKeys->get($currentPage) ?? null;      // 古い月へ
        $nextYm = $monthKeys->get($currentPage - 2) ?? null;  // 新しい月へ

        return view('attendance-list', [
            'monthLabel' => $monthLabel, // ★ 2026/01
            'items' => $items,      // ★ 各rowに date_label 追加済み
            'paginator' => $paginator,
            'prevYm' => $prevYm,
            'nextYm' => $nextYm,
        ]);
    }
}
