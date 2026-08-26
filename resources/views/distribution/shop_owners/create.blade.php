@extends('layouts.app')
@section('title', 'Add Shop Owner')

@section('content')
<section class="content-header">
    <h1>Add Shop Owner <small>Create a shop profile for the distribution team</small></h1>
</section>

<section class="content">
    @include('layouts.partials.error')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-store"></i> Owner Information</h3>
        </div>
        <form method="POST" action="{{ route('distribution.shop-owners.store') }}">
            @csrf
            <div class="box-body">
                @include('distribution.shop_owners._form')
            </div>
        </form>
    </div>
</section>
@endsection
