@extends('panel.layouts.app')

@section('title', 'Pricing & Rates')

@section('content')
    <pricing-page :can-manage-pricing='@json($canManagePricing)'></pricing-page>
@endsection
