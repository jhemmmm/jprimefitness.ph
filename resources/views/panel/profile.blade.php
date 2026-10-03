@extends('panel.layouts.app')

@section('title', 'Profile')

@section('content')
    <profile-page :user='@json($user)'></profile-page>
@endsection
