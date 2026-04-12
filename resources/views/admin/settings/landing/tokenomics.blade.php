<div class="page-title">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-inner">
                <div class="table-title mb-4">
                    <h3>{{__('Tokenomics Section')}}</h3>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="form-area plr-65 profile-info-form">
    <form method="POST" action="{{route('adminLandingSettingSave')}}">
        @csrf
        @if(isset($itech))
            <input type="hidden" name="itech" value="{{$itech}}">
        @endif

        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{__('Show Tokenomics Section')}}</label>
                    <select class="form-control" name="landing_show_tokenomics">
                        <option value="1" @if(($adm_setting['landing_show_tokenomics'] ?? '1') == '1') selected @endif>{{__('Yes')}}</option>
                        <option value="0" @if(($adm_setting['landing_show_tokenomics'] ?? '1') == '0') selected @endif>{{__('No')}}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-5">
                <div class="form-group">
                    <label>{{__('Section Title')}}</label>
                    <input type="text" class="form-control" name="tokenomics_section_title"
                           value="{{$adm_setting['tokenomics_section_title'] ?? 'Token Distribution'}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Section Subtitle')}}</label>
                    <input type="text" class="form-control" name="tokenomics_section_subtitle"
                           value="{{$adm_setting['tokenomics_section_subtitle'] ?? ''}}">
                </div>
            </div>
        </div>

        <p class="text-muted mb-3">{{__('Set the label and percentage for each allocation. Percentages should add up to 100%.')}}</p>

        @php
            $tSlices = [
                ['key' => 'public_sale',  'defaultLabel' => 'Public Sale',     'defaultPct' => '40'],
                ['key' => 'team',         'defaultLabel' => 'Team & Advisors', 'defaultPct' => '15'],
                ['key' => 'ecosystem',    'defaultLabel' => 'Ecosystem Fund',  'defaultPct' => '20'],
                ['key' => 'reserve',      'defaultLabel' => 'Reserve',         'defaultPct' => '10'],
                ['key' => 'liquidity',    'defaultLabel' => 'Liquidity',       'defaultPct' => '10'],
                ['key' => 'marketing',    'defaultLabel' => 'Marketing',       'defaultPct' => '5'],
            ];
        @endphp

        <div class="row">
            @foreach($tSlices as $slice)
            <div class="col-md-4">
                <div class="card p-3 mb-3" style="background:var(--card, #f8f9fa);border:1px solid #e0e0e0;border-radius:8px;">
                    <div class="form-group mb-2">
                        <label>{{__('Label')}}</label>
                        <input type="text" class="form-control" name="tokenomics_{{$slice['key']}}_label"
                               value="{{$adm_setting['tokenomics_'.$slice['key'].'_label'] ?? $slice['defaultLabel']}}">
                    </div>
                    <div class="form-group mb-0">
                        <label>{{__('Percentage (%)')}}</label>
                        <input type="number" class="form-control" name="tokenomics_{{$slice['key']}}_pct"
                               min="0" max="100" step="0.1"
                               value="{{$adm_setting['tokenomics_'.$slice['key'].'_pct'] ?? $slice['defaultPct']}}">
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <button class="button-primary theme-btn">{{__('Update')}}</button>
    </form>
</div>
