@extends('home.layouts.app')

@section('title', sprintf('%s - Branch Details', $branch->name))

@section('content')
    <branch-component></branch-component>
@endsection
