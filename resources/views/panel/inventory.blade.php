@extends('panel.layouts.app')

@section('title', 'Inventory')

@section('content')
    <inventory-page :categories-data='@json($inventoryCategories)'></inventory-page>
@endsection
