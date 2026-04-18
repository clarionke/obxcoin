@extends('user.master',['menu'=>'referral','sub_menu'=>'referral_tree'])
@section('title', isset($title) ? $title : '')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card cp-user-custom-card">
                <div class="card-body">
                    <div class="cp-user-card-header-area cp-user-card-header-bb d-flex justify-content-between align-items-center flex-wrap">
                        <h4>{{__('Referral Tree')}}</h4>
                        <a href="{{ route('myReferral') }}" class="btn btn-outline-primary">{{__('Back to Referrals')}}</a>
                    </div>
                    <div class="row mt-3">
                        <div class="col-lg-8">
                            <p class="mb-2"><strong>{{__('Referral Link')}}:</strong></p>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <button onclick="CopyReferralTreeUrl()" type="button" class="btn copy-url-btn">{{__('Copy URL')}}</button>
                                </div>
                                <input type="url" class="form-control" id="tree-referral-url" value="{{ $url }}" readonly>
                            </div>
                            <div class="alert alert-info mb-0">
                                {{__('Referral rewards are paid as real OBXCoin on-chain. Gas is sponsored by the platform signer, so recipients do not need native gas to receive these rewards.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card cp-user-custom-card mt-4">
                <div class="card-body">
                    <div class="cp-user-card-header-area">
                        <h4>{{__('My Upline')}}</h4>
                    </div>
                    @if(count($upline) > 0)
                        <div class="table-responsive mt-3">
                            <table class="table cp-user-custom-table table-borderless text-center">
                                <thead>
                                <tr>
                                    <th>{{__('Level')}}</th>
                                    <th>{{__('Name')}}</th>
                                    <th>{{__('Email')}}</th>
                                    <th>{{__('Joined')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($upline as $node)
                                    <tr>
                                        <td>{{__('Level')}} {{ $node['level'] }}</td>
                                        <td>{{ trim($node['user']->first_name.' '.$node['user']->last_name) }}</td>
                                        <td>{{ $node['user']->email }}</td>
                                        <td>{{ $node['user']->created_at }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="mb-0 mt-3">{{__('No upline found for this account.') }}</p>
                    @endif
                </div>
            </div>

            <div class="card cp-user-custom-card mt-4">
                <div class="card-body">
                    <div class="cp-user-card-header-area">
                        <h4>{{__('My Downline')}}</h4>
                    </div>
                    <p class="text-muted mt-2">{{__('Showing the unilevel referral tree up to level')}} {{ $max_referral_level }}.</p>
                    @if(count($downline_tree) > 0)
                        <div class="referral-tree-wrapper mt-3">
                            <ul class="list-unstyled mb-0">
                                @foreach($downline_tree as $node)
                                    @include('user.referral.partials.tree_node', ['node' => $node])
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="mb-0">{{__('You do not have any downline referrals yet.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('style')
    <style>
        .referral-tree-wrapper ul {
            padding-left: 1.25rem;
            border-left: 1px solid rgba(0, 0, 0, 0.08);
        }

        .referral-tree-node {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }

        .referral-tree-meta {
            color: #6c757d;
            font-size: 13px;
        }
    </style>
@endsection

@section('script')
    <script>
        function CopyReferralTreeUrl() {
            var copyText = document.getElementById('tree-referral-url');
            copyText.select();
            document.execCommand('copy');
            VanillaToasts.create({
                text: '{{__('URL copied successfully')}}',
                type: 'success',
                timeout: 3000
            });
        }
    </script>
@endsection