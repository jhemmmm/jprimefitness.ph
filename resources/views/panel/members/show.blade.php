@extends('panel.layouts.app')

@section('title', $memberName . ' - Member')

@section('content')
    <member-detail-page :member='@json($member)'></member-detail-page>
@endsection
