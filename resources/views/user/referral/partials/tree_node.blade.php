<li>
    <div class="referral-tree-node">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <strong>{{ trim($node['user']->first_name.' '.$node['user']->last_name) }}</strong>
                <div class="referral-tree-meta">{{ $node['user']->email }}</div>
            </div>
            <div class="text-right">
                <div><strong>{{__('Level')}} {{ $node['level'] }}</strong></div>
                <div class="referral-tree-meta">{{ $node['user']->created_at }}</div>
            </div>
        </div>
    </div>
    @if(!empty($node['children']))
        <ul class="list-unstyled">
            @foreach($node['children'] as $childNode)
                @include('user.referral.partials.tree_node', ['node' => $childNode])
            @endforeach
        </ul>
    @endif
</li>