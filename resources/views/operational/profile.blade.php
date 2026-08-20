@extends('layouts.operational')

@section('operational-content')
@php
  $profile = $user->profile;
  $operationalRoutePrefix = $user->hasRole('proctor') ? 'proctor' : 'instructor';
  $value = fn (string $field, mixed $fallback = '') => old($field, $fallback ?? '');
  $certificateDocument = $certificate?->documents?->first();
  $birthdayMonth = old('birthday_month', $profile?->birthday?->format('n'));
  $birthdayDay = old('birthday_day', $profile?->birthday?->format('j'));
  $birthdayYear = old('birthday_year', $profile?->birthday?->format('Y'));
@endphp

<section class="panel profile-welcome">
  <div class="panel-title">Welcome to Your Account</div>
  <div class="panel-body">
    <img src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" />
    <div>
      <h1>Welcome to your IADC WellSharp Testing Portal</h1>
      <p>Please keep your name and contact information up to date so that we have accurate records for scheduling and reporting purposes.</p>
    </div>
  </div>
</section>

<form class="panel" method="POST" action="{{ route($operationalRoutePrefix.'.profile.update') }}" enctype="multipart/form-data"
      x-data="{ photoPreview: @js($profilePhotoUrl), showPasswordBox: false, photoDialogOpen: false }">
  @csrf
  @method('PATCH')
  <div class="panel-title">Update Your Profile</div>

  <div class="profile-form">
    <div class="profile-fields-left">
      <div class="form-line">
        <label for="first_name">Name:</label>
        <div class="split-inputs">
          <input id="first_name" name="first_name" value="{{ $value('first_name', $profile?->first_name) }}" autocomplete="given-name" required />
          <input name="last_name" value="{{ $value('last_name', $profile?->last_name) }}" autocomplete="family-name" required />
        </div>
      </div>
      <div class="form-line">
        <label for="birthday_month">Date of Birth:</label>
        <div class="dob-inputs">
          <select id="birthday_month" name="birthday_month">
            <option value="">Month</option>
            @foreach(\Illuminate\Support\Carbon::createFromDate(2000, 1, 1)->monthsUntil('2000-12-31') as $month)
              <option value="{{ $month->month }}" @selected((int) $birthdayMonth === $month->month)>{{ $month->format('F') }}</option>
            @endforeach
          </select>
          <select name="birthday_day">
            <option value="">Day</option>
            @foreach(range(1, 31) as $day)
              <option value="{{ $day }}" @selected((int) $birthdayDay === $day)>{{ $day }}</option>
            @endforeach
          </select>
          <select name="birthday_year">
            <option value="">Year</option>
            @foreach(range(now()->year, now()->year - 100) as $year)
              <option value="{{ $year }}" @selected((int) $birthdayYear === $year)>{{ $year }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="form-line">
        <label for="email">Email:</label>
        <input id="email" name="email" type="email" value="{{ $value('email', $user->email) }}" autocomplete="email" />
      </div>
      <div class="form-line">
        <label for="phone">Phone Number:</label>
        <input id="phone" name="phone" value="{{ $value('phone', $profile?->phone) }}" autocomplete="tel" />
      </div>
      <div class="form-line">
        <label for="address">Address:</label>
        <input id="address" name="address" value="{{ $value('address', $profile?->address) }}" autocomplete="street-address" />
      </div>
      <div class="form-line">
        <label for="country">Country:</label>
        <input id="country" name="country" value="{{ $value('country', $profile?->country) }}" autocomplete="country-name" />
      </div>
      <div class="form-line">
        <label for="state">State:</label>
        <input id="state" name="state" class="state-select" value="{{ $value('state', $profile?->state) }}" autocomplete="address-level1" />
      </div>
      <div class="form-line">
        <label for="city">City:</label>
        <input id="city" name="city" class="city-input" value="{{ $value('city', $profile?->city) }}" autocomplete="address-level2" />
      </div>
      <div class="form-line">
        <label for="postal_code">Postal Code:</label>
        <input id="postal_code" name="postal_code" class="postal-input" value="{{ $value('postal_code', $profile?->postal_code) }}" autocomplete="postal-code" />
      </div>
    </div>

    <div class="profile-fields-right">
      <div class="form-line">
        <label for="company">Company:</label>
        <input id="company" name="company" value="{{ $value('company', $profile?->company) }}" autocomplete="organization" />
      </div>
      <div class="form-line">
        <label for="position">Position:</label>
        <input id="position" name="position" value="{{ $value('position', $profile?->position) }}" autocomplete="organization-title" />
      </div>
      <div class="form-line">
        <label for="employee_id">Employee ID:</label>
        <input id="employee_id" name="employee_id" value="{{ $value('employee_id', $profile?->employee_id) }}" />
      </div>
      <div class="form-line password-line">
        <label>Password:</label>
        <button class="password-btn" type="button" x-show="!showPasswordBox" x-on:click="showPasswordBox = true">Change Password</button>
        <div class="password-change-box" x-show="showPasswordBox" x-cloak>
          <div class="password-row"><span>New Password:</span><input name="password" type="password" autocomplete="new-password" minlength="12" /></div>
          <div class="password-row"><span>Retype New Password:</span><input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" /></div>
          <div class="password-actions">
            <button class="update-password" type="button" x-on:click="showPasswordBox = false">Update Password</button>
            <button class="cancel-password" type="button" x-on:click="showPasswordBox = false">Cancel</button>
          </div>
        </div>
        @error('password')<p class="profile-field-error">{{ $message }}</p>@enderror
      </div>

      <button class="profile-photo" type="button" x-on:click="photoDialogOpen = true" x-bind:style="photoPreview ? ('background-image:url(' + photoPreview + ')') : ''" aria-label="Select profile picture">
        <span class="photo-close" x-show="photoPreview" x-on:click.stop="photoPreview = null; $refs.photoInput.value = ''">x</span>
      </button>
      <input class="profile-file-input" x-ref="photoInput" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp"
             x-on:change="photoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : photoPreview; photoDialogOpen = false" />

      <div class="profile-photo-overlay" x-show="photoDialogOpen" x-cloak x-on:click="photoDialogOpen = false"></div>
      <section class="profile-photo-dialog" x-show="photoDialogOpen" x-cloak role="dialog" aria-modal="true">
        <h2>Select profile picture</h2>
        <div class="profile-photo-preview" x-bind:style="photoPreview ? ('background-image:url(' + photoPreview + ');background-size:cover;background-position:center') : ''"></div>
        <div class="profile-photo-actions">
          <button type="button" class="photo-dialog-close" x-on:click="photoDialogOpen = false">Close</button>
          <button type="button" class="photo-dialog-select" x-on:click="$refs.photoInput.click()">Select photo</button>
        </div>
      </section>

      @if($certificateDocument)
        <a class="cyan-btn" href="{{ route('certificates.documents.download', [$certificate, $certificateDocument]) }}">Download Certificate</a>
      @else
        <a class="cyan-btn" href="{{ route($operationalRoutePrefix.'.certificate') }}">Certificate Lookup</a>
      @endif
    </div>

    <div class="save-profile"><button class="green-btn" type="submit">Save Profile</button></div>
  </div>
</form>
@endsection
