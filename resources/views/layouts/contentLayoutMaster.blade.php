@inject('request', 'Illuminate\Http\Request')
@isset($pageConfigs)
    {!! Helper::updatePageConfig($pageConfigs) !!}
@endisset

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title') - {{ env('APP_NAME') }}</title>
        <link rel="shortcut icon" type="image/x-icon" href="/images/logo/favicon.ico">

        {{-- Include core + vendor Styles --}}
        @include('panels/styles')
        {{-- Include page Style --}}
        @yield('mystyle')

    </head>

    {{-- {!! Helper::applClasses() !!} --}}
    @php
        $configData = Helper::applClasses();
    @endphp

    @extends((( $configData["mainLayoutType"] === 'horizontal') ? 'layouts/horizontalLayoutMaster' : 'layouts.verticalLayoutMaster' ))
