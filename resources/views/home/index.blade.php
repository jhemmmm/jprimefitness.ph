@extends('home.layouts.app')

@section('title', $businessProfile->name . ' - Train with purpose')

@section('content')
    <section class="py-5 text-white" style="background: linear-gradient(135deg, #111827 0%, #7f1d1d 100%); min-height: 78vh;">
        <div class="container pt-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="text-uppercase small fw-bold mb-3" style="letter-spacing: 0.18em; color: rgba(255,255,255,.72);">
                        JPRIME Fitness
                    </div>
                    <h1 class="display-4 fw-bold mb-3">
                        Train with purpose.
                        <span class="text-danger">Build real progress.</span>
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        A cleaner, simpler fitness experience built around clear pricing, consistent coaching, and practical day-to-day training.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#pricing" class="btn btn-danger px-4 py-2 fw-semibold rounded-1">View Pricing</a>
                        <a href="#about" class="btn btn-outline-light px-4 py-2 fw-semibold rounded-1">About the Gym</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="bg-white text-dark rounded-4 shadow-lg p-4">
                        <div class="small text-uppercase text-muted fw-bold mb-3" style="letter-spacing: 0.12em;">Location</div>
                        <h2 class="h4 fw-bold mb-2">{{ $businessProfile->name }}</h2>
                        <p class="text-muted mb-3">
                            {{ collect([$businessProfile->address, $businessProfile->city, $businessProfile->province])->filter()->join(', ') ?: 'Address details coming soon.' }}
                        </p>
                        <div class="small text-muted mb-1">Hours</div>
                        <div class="fw-semibold mb-3">
                            @if ($businessProfile->opening_time && $businessProfile->closing_time)
                                {{ \Illuminate\Support\Carbon::parse($businessProfile->opening_time)->format('g:i A') }}
                                -
                                {{ \Illuminate\Support\Carbon::parse($businessProfile->closing_time)->format('g:i A') }}
                            @else
                                Hours to be announced
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light" id="about">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-6">
                    <div class="text-uppercase small fw-bold text-danger mb-2" style="letter-spacing: 0.14em;">About</div>
                    <h2 class="fw-bold mb-3">Clear standards. Real progress.</h2>
                    <p class="text-muted mb-0">
                        We keep the training experience straightforward: organized operations, visible pricing, useful equipment, and a reliable place for members to train and improve.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                                <div class="fw-bold mb-2">Amenities</div>
                                @if (($businessProfile->amenities ?? []) !== [])
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($businessProfile->amenities as $amenity)
                                            <span class="badge text-bg-light border">{{ $amenity }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-muted small">Amenities will be added here.</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                                <div class="fw-bold mb-2">Timezone</div>
                                <div class="text-muted">{{ $businessProfile->timezone ?: 'Asia/Manila' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="pricing">
        <div class="container">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
                <div>
                    <div class="text-uppercase small fw-bold text-danger mb-2" style="letter-spacing: 0.14em;">Pricing</div>
                    <h2 class="fw-bold mb-1">Memberships and PT packages</h2>
                    <p class="text-muted mb-0">All current prices are managed centrally for this gym.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                        <h3 class="h5 fw-bold mb-3">Membership Plans</h3>
                        @if ($ratePlans->isEmpty())
                            <div class="text-muted">Membership pricing will be published soon.</div>
                        @else
                            <div class="vstack gap-3">
                                @foreach ($ratePlans as $ratePlan)
                                    <div class="border rounded-3 p-3 d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $ratePlan->name }}</div>
                                            <div class="text-muted small">{{ $ratePlan->description ?: 'Membership plan' }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold fs-5">₱{{ number_format((float) $ratePlan->price, 2) }}</div>
                                            <div class="text-muted small">{{ $ratePlan->duration_days }} days</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="bg-white rounded-4 shadow-sm p-4 h-100">
                        <h3 class="h5 fw-bold mb-3">PT Packages</h3>
                        @if ($ptProducts->isEmpty())
                            <div class="text-muted">PT pricing will be published soon.</div>
                        @else
                            <div class="vstack gap-3">
                                @foreach ($ptProducts as $ptProduct)
                                    <div class="border rounded-3 p-3 d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $ptProduct->name }}</div>
                                            <div class="text-muted small">{{ $ptProduct->description ?: ($ptProduct->category ?: 'PT package') }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold fs-5">₱{{ number_format((float) $ptProduct->price, 2) }}</div>
                                            <div class="text-muted small">{{ $ptProduct->session_count }} sessions</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
