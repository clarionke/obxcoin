<div class="page-title">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-inner">
                <div class="table-title mb-4">
                    <h3>{{__('Token Info Bar & Social Links')}}</h3>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="form-area plr-65 profile-info-form">
    <form enctype="multipart/form-data" method="POST" action="{{route('adminLandingSettingSave')}}">
        @csrf
        @if(isset($itech))
            <input type="hidden" name="itech" value="{{$itech}}">
        @endif

        <h5 class="mb-3">{{__('Hero Section')}}</h5>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{__('Hero Badge Text')}}</label>
                    <input type="text" class="form-control" name="landing_hero_badge"
                           value="{{$adm_setting['landing_hero_badge'] ?? 'Live & Secure Platform'}}">
                    <small class="text-muted">{{__('Short text shown above the main headline (e.g. "Now Live on Mainnet")')}}</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{__('Whitepaper URL')}}</label>
                    <input type="url" class="form-control" name="whitepaper_url"
                           value="{{$adm_setting['whitepaper_url'] ?? ''}}">
                    <small class="text-muted">{{__('Leave blank to hide the Whitepaper button')}}</small>
                </div>
            </div>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">{{__('Token Info Bar')}}</h5>
        <div class="row align-items-center mb-3">
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Show Token Info Bar')}}</label>
                    <select class="form-control" name="landing_show_token_info">
                        <option value="1" @if(($adm_setting['landing_show_token_info'] ?? '1') == '1') selected @endif>{{__('Yes')}}</option>
                        <option value="0" @if(($adm_setting['landing_show_token_info'] ?? '1') == '0') selected @endif>{{__('No')}}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Blockchain / Network Name')}}</label>
                    <input type="text" class="form-control" name="coin_blockchain_name"
                           value="{{$adm_setting['coin_blockchain_name'] ?? 'Ethereum'}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Launch Date')}}</label>
                    <input type="text" class="form-control" name="coin_launch_date"
                           placeholder="e.g. Q1 2025"
                           value="{{$adm_setting['coin_launch_date'] ?? ''}}">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{__('Contract Address')}}</label>
                    <input type="text" class="form-control" name="contract_address"
                           value="{{$adm_setting['contract_address'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{__('Total Supply')}}</label>
                    <input type="number" class="form-control" name="obx_total_supply"
                           value="{{$adm_setting['obx_total_supply'] ?? '100000000'}}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{__('Explorer / Chain Link URL')}}</label>
                    <input type="text" class="form-control" name="chain_link"
                           value="{{$adm_setting['chain_link'] ?? ''}}">
                </div>
            </div>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">{{__('Social & Community Links')}}</h5>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Telegram')}}</label>
                    <input type="url" class="form-control" name="landing_telegram_link"
                           placeholder="https://t.me/..." value="{{$adm_setting['landing_telegram_link'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Discord')}}</label>
                    <input type="url" class="form-control" name="landing_discord_link"
                           placeholder="https://discord.gg/..." value="{{$adm_setting['landing_discord_link'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('GitHub')}}</label>
                    <input type="url" class="form-control" name="landing_github_link"
                           placeholder="https://github.com/..." value="{{$adm_setting['landing_github_link'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Twitter / X')}}</label>
                    <input type="url" class="form-control" name="landing_twitter_link"
                           value="{{$adm_setting['landing_twitter_link'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Facebook')}}</label>
                    <input type="url" class="form-control" name="landing_facebook_link"
                           value="{{$adm_setting['landing_facebook_link'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('Instagram')}}</label>
                    <input type="url" class="form-control" name="landing_instagram_link"
                           value="{{$adm_setting['landing_instagram_link'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('LinkedIn')}}</label>
                    <input type="url" class="form-control" name="landing_linkedin_link"
                           value="{{$adm_setting['landing_linkedin_link'] ?? ''}}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{__('YouTube')}}</label>
                    <input type="url" class="form-control" name="landing_youtube_link"
                           value="{{$adm_setting['landing_youtube_link'] ?? ''}}">
                </div>
            </div>
        </div>

        <button class="button-primary theme-btn">{{__('Update')}}</button>
    </form>
</div>
