@extends('home.layouts.app')

@section('title', $businessProfile->name . ($businessProfile->city ? ' - Gym in ' . $businessProfile->city : ''))

@push('styles')
    <link rel="preload" as="image" href="/images/gym/floor.webp" fetchpriority="high">
@endpush

@section('content')
    <home-component :business='@json($businessProfile)' :rate-plans='@json($ratePlans)' :pt-products='@json($ptProducts)'></home-component>
@endsection
