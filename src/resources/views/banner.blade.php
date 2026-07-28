{{-- Manual include: @include('imitator::banner') when inject_banner is false --}}
@php($status = $status ?? app(\Uadevteampackages\Imitator\Imitator::class)->status())
@if ($status['active'] ?? false)
    <div id="imitator-banner" style="border-bottom:1px solid #e0b3b3;background:#f8e8e8;color:#4a1010;padding:0.65rem 1rem;font-family:IBM Plex Sans,Segoe UI,sans-serif;font-size:0.95rem;">
        <div style="max-width:1100px;margin:0 auto;display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap;">
            <div>
                <strong>Imitating {{ $status['targetUser']['name'] ?? 'user' }}</strong>
                <span style="opacity:0.8;">
                    · original {{ $status['originalUser']['name'] ?? 'user' }}
                </span>
            </div>
            <form method="post" action="{{ url('/imitator') }}" style="margin:0;">
                @csrf
                @method('DELETE')
                <button type="submit" style="appearance:none;border:1px solid #c98989;background:#fff;color:#4a1010;padding:0.4rem 0.75rem;cursor:pointer;font:inherit;">
                    Stop imitation
                </button>
            </form>
        </div>
    </div>
@endif
