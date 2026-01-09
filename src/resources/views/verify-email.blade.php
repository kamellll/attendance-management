@extends('layouts.simple')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/verify.css') }}">
@endsection

@section('content')
    <div class="verify">
        <p>登録していただいたメールアドレスに認証メールを送付しました。<br />メール認証を完了してください。</p>
        <div class="verify__link"><a href="http://localhost:8025/" target="_blank">認証はこちらから</a></div>
        <form action="{{ route('verification.send') }}" method="post">
            @csrf
            <button type="submit">認証メールを再送する</button>
        </form>
    </div>
@endsection