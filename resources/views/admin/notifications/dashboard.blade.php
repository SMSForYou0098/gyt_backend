@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>FCM Tokens</h1>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Total Tokens</div>
                <div class="card-body">
                    <h2>{{ $stats['total'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">With User ID</div>
                <div class="card-body">
                    <h2>{{ $stats['with_user_id'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Without User ID</div>
                <div class="card-body">
                    <h2>{{ $stats['without_user_id'] }}</h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">All Tokens</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Token (truncated)</th>
                        <th>User ID</th>
                        <th>Device</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokens as $token)
                    <tr>
                        <td>{{ $token->id }}</td>
                        <td title="{{ $token->token }}">{{ substr($token->token, 0, 30) }}...</td>
                        <td>{{ $token->user_id ?? 'Not logged in' }}</td>
                        <td>{{ $token->device_info ? substr($token->device_info, 0, 30) : 'Unknown' }}</td>
                        <td>{{ $token->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            {{ $tokens->links() }}
        </div>
    </div>
</div>
@endsection