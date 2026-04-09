@extends('panel.layouts.app')

@section('title', 'Cash Ledger')

@section('content')
    <business-cash-ledger-page :profile='@json($businessProfile)'></business-cash-ledger-page>
@endsection
