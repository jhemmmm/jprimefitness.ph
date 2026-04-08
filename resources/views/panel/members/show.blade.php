@extends('panel.layouts.app')

@section('title', $memberName . ' - Member')

@section('content')
    <member-detail-page :member='@json($member)' :rate-plans-data='@json($ratePlans)'></member-detail-page>
@endsection
