@extends('home.layouts.app')

@section('title', $businessProfile->name . ' - Train with purpose')

@section('content')
    <home-component :business='@json($businessProfile)' :rate-plans='@json($ratePlans)' :pt-products='@json($ptProducts)'></home-component>
@endsection
