@extends('panel.layouts.app')

@section('title', 'Members')

@section('content')
    <members-page :branches-data='@json($branches)'
        :rate-plans-data='@json($ratePlans)'></members-page>
@endsection
