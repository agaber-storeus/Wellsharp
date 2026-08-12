@extends('layouts.operational')

@section('operational-content')
@php
  $profile = $user->profile;
  $operationalRoutePrefix = $user->hasRole('proctor') ? 'proctor' : 'instructor';
  $value = fn (string $field, mixed $fallback = '') => old($field, $fallback ?? '');
  $certificateDocument = $certificate?->documents?->first();
@endphp

<section class="panel profile-welcome">
  <div class="panel-title">Welcome to Your Account</div>
  <div class="panel-body">
    <img src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" />
    <div>
      <h1>Welcome to your IADC WellSharp Testing Portal</h1>
      <p>Keep your personal, contact, and work information up to date for scheduling and reporting.</p>
    </div>
  </div>
</section>

<form class="panel standard-profile-panel redesigned-profile-panel" method="POST" action="{{ route($operationalRoutePrefix.'.profile.update') }}" enctype="multipart/form-data" x-data="{ photoPreview: @js($profilePhotoUrl) }">
  @csrf
  @method('PATCH')
  <div class="panel-title">Update Your Profile</div>

  <div class="redesigned-profile-layout">
    <div class="profile-main-column">
      <section class="profile-section">
        <div class="profile-section-heading"><div><h2>Personal information</h2><p>Your identity and login contact details.</p></div></div>
        <div class="profile-section-grid">
          <div class="profile-field"><label for="first_name">First name <span>*</span></label><input id="first_name" name="first_name" value="{{ $value('first_name', $profile?->first_name) }}" autocomplete="given-name" required /></div>
          <div class="profile-field"><label for="last_name">Last name <span>*</span></label><input id="last_name" name="last_name" value="{{ $value('last_name', $profile?->last_name) }}" autocomplete="family-name" required /></div>
          <div class="profile-field"><label for="birthday">Date of birth</label><input id="birthday" name="birthday" type="date" value="{{ $value('birthday', $profile?->birthday?->format('Y-m-d')) }}" autocomplete="bday" /></div>
          <div class="profile-field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ $value('email', $user->email) }}" autocomplete="email" /></div>
        </div>
      </section>

      <section class="profile-section">
        <div class="profile-section-heading"><div><h2>Contact and address</h2><p>Where we can reach you and your location.</p></div></div>
        <div class="profile-section-grid">
          <div class="profile-field profile-field-wide"><label for="phone">Phone number</label><input id="phone" name="phone" value="{{ $value('phone', $profile?->phone) }}" autocomplete="tel" /></div>
          <div class="profile-field profile-field-wide"><label for="address">Address</label><input id="address" name="address" value="{{ $value('address', $profile?->address) }}" autocomplete="street-address" /></div>
          <div class="profile-field"><label for="country">Country</label><input id="country" name="country" value="{{ $value('country', $profile?->country) }}" autocomplete="country-name" /></div>
          <div class="profile-field"><label for="state">State</label><input id="state" name="state" value="{{ $value('state', $profile?->state) }}" autocomplete="address-level1" /></div>
          <div class="profile-field"><label for="city">City</label><input id="city" name="city" value="{{ $value('city', $profile?->city) }}" autocomplete="address-level2" /></div>
          <div class="profile-field"><label for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ $value('postal_code', $profile?->postal_code) }}" autocomplete="postal-code" /></div>
        </div>
      </section>
    </div>

    <aside class="profile-side-column">
      <section class="profile-section">
        <div class="profile-section-heading"><div><h2>Work information</h2><p>Your current company and role.</p></div></div>
        <div class="profile-section-grid profile-section-grid-single">
          <div class="profile-field"><label for="company">Company</label><input id="company" name="company" value="{{ $value('company', $profile?->company) }}" autocomplete="organization" /></div>
          <div class="profile-field"><label for="position">Position</label><input id="position" name="position" value="{{ $value('position', $profile?->position) }}" autocomplete="organization-title" /></div>
          <div class="profile-field"><label for="employee_id">Employee ID</label><input id="employee_id" name="employee_id" value="{{ $value('employee_id', $profile?->employee_id) }}" /></div>
          @if($user->hasRole('proctor'))
            <div class="profile-field"><label for="proctor_id">Exam-control ID</label><input id="proctor_id" value="{{ $user->examControlCredential?->control_id }}" readonly aria-describedby="proctor-id-help" /><small id="proctor-id-help">Used to authorize Class start and end controls.</small></div>
          @endif
        </div>
      </section>

      <section class="profile-section profile-security-section">
        <div class="profile-section-heading"><div><h2>Account security</h2><p>Change your password when needed.</p></div></div>
        <div class="profile-section-grid profile-section-grid-single">
          <div class="profile-field"><label for="password">New password <small>(optional)</small></label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" aria-describedby="password-help" /></div>
          <div class="profile-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" /></div>
        </div>
        <p class="profile-help" id="password-help">Leave both fields blank to keep your current password. New passwords require at least 12 characters and are securely hashed.</p>
        @error('password')<p class="profile-field-error">{{ $message }}</p>@enderror
      </section>

      <section class="profile-section profile-photo-section">
        <div class="profile-section-heading"><div><h2>Profile photo</h2><p>Optional JPG, PNG, or WebP image.</p></div></div>
        <div class="profile-photo-picker">
          <button class="profile-photo profile-photo-modern" type="button" x-on:click="$refs.photoInput.click()" aria-label="Select profile picture">
            <img x-show="photoPreview" x-bind:src="photoPreview" alt="Profile photo preview" x-cloak />
            <img x-show="!photoPreview" src="{{ asset('images/profile-placeholder.png') }}" alt="Profile photo placeholder" />
            <span class="profile-photo-overlay">Choose image</span>
          </button>
          <input class="profile-file-input" x-ref="photoInput" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" x-on:change="photoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : photoPreview" />
        </div>
      </section>

      @if($certificateDocument)
        <a class="cyan-btn profile-certificate-action" href="{{ route('certificates.documents.download', [$certificate, $certificateDocument]) }}">Download Certificate</a>
      @else
        <a class="cyan-btn profile-certificate-action" href="{{ route($operationalRoutePrefix.'.certificate') }}">Certificate Lookup</a>
      @endif
    </aside>
  </div>

  <div class="save-profile redesigned-save-profile"><button class="green-btn" type="submit">Save Profile</button></div>
</form>
@endsection
