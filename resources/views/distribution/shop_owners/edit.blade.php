@extends('layouts.app')
@section('title', 'Edit Shop Owner')

@section('content')
<section class="content-header">
    <h1>Edit Shop Owner <small>Update {{ $owner->name }}</small></h1>
</section>

<section class="content">
    @include('layouts.partials.error')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-edit"></i> Owner Information</h3>
        </div>
        <form method="POST" action="{{ route('distribution.shop-owners.update', $owner) }}">
            @csrf
            @method('PUT')
            <div class="box-body">
                @include('distribution.shop_owners._form')
            </div>
        </form>
    </div>
</section>
@endsection
