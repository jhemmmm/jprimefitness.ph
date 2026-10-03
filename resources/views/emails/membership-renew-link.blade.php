@extends('emails.layouts.branded')

@section('title', 'Your renewal link')
@section('preheader', 'You already have a membership with us - renew it in a minute.')
@section('eyebrow', 'Welcome Back')
@section('heading', 'You\'re already a member, ' . $member->name)

@section('content')
    <p style="margin:0 0 16px 0;">
        Someone just tried to sign up at <strong>{{ $business->name }}</strong> with this email - and it already has a membership record. No need to register again: use the button below to renew, and your details stay exactly as they are on file.
    </p>

    @include('emails.partials.renew-cta', [
        'button' => 'Renew my membership',
        'lead' => 'Pick a plan, pay online or at the front desk, and your new QR code arrives by email.',
    ])

    <p style="margin:20px 0 0 0;color:#94a3b8;font-size:12px;">If this wasn't you, you can ignore this email - nothing has changed on your account.</p>

    <p style="margin:20px 0 0 0;color:#475569;">See you at the gym,<br><strong>The {{ $business->name }} team</strong></p>
@endsection
