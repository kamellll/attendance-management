@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('content')
    <div class="attendance">
        <div class="attendance__box">
            <div class="attendance__condition">
                {{ $statusStr }}
            </div>
            <div class="attendance__date">
                {{ $dateWithWeek }}
            </div>
            <div class="attendance__time">
                {{ $time }}
            </div>
            @if ($status == 0)
                <form class="form" action="/attendance/regist" method="post">
                    @csrf
                    <div class="form__button">
                        <button class="form__button-submit" type="submit">出勤</button>
                    </div>
                </form>
            @elseif($status == 1)
                <div class="attendance__form">
                    <form class="form" action="/attendance/back" method="post">
                        @csrf
                        <div class="form__button">
                            <button class="form__button-submit" type="submit" name="back">退勤</button>
                        </div>
                    </form>
                    <form class="form" action="/attendance/rest" method="post">
                        @csrf
                        <div class="form__button">
                            <button class="form__button-rest" type="submit" name="start-rest">休憩入</button>
                        </div>
                    </form>
                </div>
            @elseif($status == 2)
                <form class="form" action="/attendance/backRest" method="post">
                    @csrf
                    <div class="form__button">
                        <button class="form__button-rest" type="submit" name="back">休憩戻</button>
                    </div>
                </form>
            @elseif($status == 3)
                <p class="backed">お疲れさまでした。</p>
            @endif
        </div>
    </div>
@endsection