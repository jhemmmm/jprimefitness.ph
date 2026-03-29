@extends('panel.layouts.app')

@section('title', 'Pricing & Rates')

@section('content')
    <pricing-page :branches-data='@json($branches)' :can-manage-pricing='@json($canManagePricing)'></pricing-page>
@endsection
