@extends('panel.layouts.app')

@section('title', $member->name . ' - Member')

@section('content')
    <member-detail-page :member='@json($member)' :branches-data='@json($branches)'
        :rate-plans-data='@json($ratePlans)'></member-detail-page>
@endsection
