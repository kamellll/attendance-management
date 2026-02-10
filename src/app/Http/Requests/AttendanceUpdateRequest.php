<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use App\Models\Status;

class AttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'statuses_id' => ['required', 'integer', 'min:1'],

            'go' => ['required', 'date_format:H:i'],
            'back' => ['required', 'date_format:H:i'],

            'rest_ids' => ['required', 'array', 'min:1'],
            'rest_ids.*' => ['integer', 'min:1'],

            'start' => ['required', 'array', 'min:1'],
            'start.*' => ['required', 'date_format:H:i'],

            'end' => ['required', 'array', 'min:1'],
            'end.*' => ['nullable', 'date_format:H:i'],

            // ④ note 必須
            'note' => ['required', 'string'],

            'new_start' => ['nullable', 'date_format:H:i'],
            'new_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => '備考を記入してください',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $statusId = (int) $this->input('statuses_id');

            // 基準日を statuses の go の日付から取る（編集対象の勤怠日）
            $isAdmin = auth()->check() && (int) auth()->user()->admin === 1;

            $q = Status::query()
                ->select('id', 'user_id', 'go')
                ->where('id', $statusId);

            if (!$isAdmin) {
                // 一般ユーザーは本人のものだけ
                $q->where('user_id', auth()->id());
            }

            $status = $q->first();

            if (!$status) {
                $validator->errors()->add('statuses_id', '対象の勤怠データが見つかりません。');
                return;
            }

            $baseDate = Carbon::parse($status->go)->startOfDay();

            $goAt = $baseDate->copy()->setTimeFromTimeString($this->input('go'));
            $backAt = $baseDate->copy()->setTimeFromTimeString($this->input('back'));

            // ① go が back より後（または同時刻）ならエラー
            if ($goAt->gte($backAt)) {
                $validator->errors()->add('back', '出勤時間もしくは退勤時間が不適切な値です');
            }

            $starts = $this->input('start', []);
            $ends = $this->input('end', []);

            if (!is_array($starts) || !is_array($ends)) {
                $validator->errors()->add('start', '休憩データの形式が不正です。');
                return;
            }

            for ($i = 0; $i < count($starts); $i++) {

                // start を正規化
                $rawStart = trim((string) ($starts[$i] ?? ''));

                // ★追加用の行など：startが空ならこの行は無視してOK
                if ($rawStart === '') {
                    continue;
                }

                // start は入っているので形式チェック（H:i）
                if (!preg_match('/^\d{2}:\d{2}$/', $rawStart)) {
                    $validator->errors()->add("start.$i", "休憩開始時刻の形式が不正です。（" . ($i + 1) . "件目）");
                    continue;
                }

                $startAt = $baseDate->copy()->setTimeFromTimeString($rawStart);

                // 出勤/退勤との前後関係（startが入っているときだけチェック）
                if ($startAt->lt($goAt)) {
                    $validator->errors()->add("start.$i", "休憩時間が不適切な値です（" . ($i + 1) . "件目）");
                }
                if ($startAt->gt($backAt)) {
                    $validator->errors()->add("start.$i", "休憩時間もしくは退勤時間が不適切な値です（" . ($i + 1) . "件目）");
                }

                // end を取得（startが入っているときだけチェックする）
                $rawEnd = trim((string) ($ends[$i] ?? ''));

                // endが空なら「未入力」として許容（追加用行でもOK）
                if ($rawEnd === '') {
                    continue;
                }

                // end 形式チェック
                if (!preg_match('/^\d{2}:\d{2}$/', $rawEnd)) {
                    $validator->errors()->add("end.$i", "休憩終了時刻の形式が不正です。（" . ($i + 1) . "件目）");
                    continue;
                }

                $endAt = $baseDate->copy()->setTimeFromTimeString($rawEnd);

                // end の前後関係
                if ($endAt->lt($goAt)) {
                    $validator->errors()->add("end.$i", "休憩時間もしくは退勤時間が不適切な値です（" . ($i + 1) . "件目）");
                }
                if ($endAt->gt($backAt)) {
                    $validator->errors()->add("end.$i", "休憩時間もしくは退勤時間が不適切な値です（" . ($i + 1) . "件目）");
                }
                if ($endAt->lte($startAt)) {
                    $validator->errors()->add("end.$i", "休憩時間が不適切な値です（" . ($i + 1) . "件目）");
                }
            }
        });
    }
}
