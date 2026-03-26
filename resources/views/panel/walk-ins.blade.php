@extends('panel.layouts.app')

@section('title', 'Walk-ins')

@section('content')
    <walk-ins-page
        :branches-data='@json($branches)'
        :rate-plans-data='@json($ratePlans)'
    ></walk-ins-page>
@endsection
