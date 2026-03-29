@extends('panel.layouts.app')

@section('title', 'Inventory')

@section('content')
    <inventory-page :branches-data='@json($branches)' :categories-data='@json($inventoryCategories)'></inventory-page>
@endsection
