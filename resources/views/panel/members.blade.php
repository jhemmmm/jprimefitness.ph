@extends('panel.layouts.app')

@section('title', 'Members')

@section('content')
    <members-page :rate-plans-data='@json($ratePlans)'></members-page>
@endsection
