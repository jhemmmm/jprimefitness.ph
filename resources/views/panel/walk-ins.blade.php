@extends('panel.layouts.app')

@section('title', 'Walk-ins')

@section('content')
    <walk-ins-page :rate-plans-data='@json($ratePlans)'></walk-ins-page>
@endsection
