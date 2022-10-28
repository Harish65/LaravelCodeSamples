@extends('dashboard.layouts.master')

@section('page-title', 'Uploader')

@section('page-header')
    <h1>
        @lang('app.notification_settings')
        <small>@lang('app.manage_system_notification_settings')</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('dashboard') }}"><i class="fa fa-dashboard"></i> @lang('app.home')</a></li>
        <li><a href="javascript:;">@lang('app.settings')</a></li>
        <li class="active">@lang('app.notifications')</li>
      </ol>
@endsection

@section('content')

@include('partials.messages')

<div class="row">
    <div class="col-md-8">


    <div class="panel panel-default">
        <div class="panel-body">



        </div>
    </div>


    </div>
</div>

@stop

@section('after-scripts-end')
    <script>
        $(".switch").bootstrapSwitch({size: 'small'});
    </script>
@stop