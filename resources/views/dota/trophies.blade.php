@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Трофеи - DotaHub'))

@section('profile-content')
    <section class="mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <div>
                <h2 class="section-heading">Трофеи</h2>
                <p class="mt-0.5 text-[11px] text-mist-400">Достижения и прогресс профиля</p>
            </div>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="border border-[#2d313e] bg-[#1c1e26] p-4">
                <p class="text-[10px] uppercase tracking-wider text-mist-500">Уровень профиля Dota 2</p>
                <p class="mt-2 text-3xl font-black text-white">{{ $player['profile_level'] ?? '—' }}</p>
            </div>

            <div class="border border-[#2d313e] bg-[#1c1e26] p-4">
                <p class="text-[10px] uppercase tracking-wider text-mist-500">Текущий ранг</p>
                <div class="mt-2 flex items-center gap-3">
                    @if (!empty($player['rank_medal']))
                        <img src="{{ $player['rank_medal'] }}" alt="{{ $player['rank_label'] ?? 'Ранг' }}" class="h-12 w-12 object-contain">
                    @endif
                    <div>
                        <p class="text-lg font-bold text-white">{{ $player['rank_label'] ?? 'Без ранга' }}</p>
                        @if (!empty($player['leaderboard_rank']))
                            <p class="text-[11px] text-mist-400">#{{ number_format($player['leaderboard_rank'], 0, '.', ' ') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border border-[#2d313e] bg-[#1c1e26] p-4">
                <p class="text-[10px] uppercase tracking-wider text-mist-500">Всего матчей</p>
                <p class="mt-2 text-3xl font-black text-white">{{ number_format((int) ($player['games'] ?? 0), 0, '.', ' ') }}</p>
            </div>

            <div class="border border-[#2d313e] bg-[#1c1e26] p-4">
                <p class="text-[10px] uppercase tracking-wider text-mist-500">Часов в игре</p>
                <p class="mt-2 text-3xl font-black text-white">{{ number_format((int) ($totalHours ?? 0), 0, '.', ' ') }}</p>
            </div>
        </div>

        <h3 class="mt-6 mb-3 text-xs font-semibold uppercase tracking-wider text-mist-400">Достижения</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($trophies as $trophy)
                <div @class([
                    'border p-4 transition',
                    'border-yellow-500/50 bg-yellow-500/10' => $trophy['achieved'],
                    'border-[#2d313e] bg-[#1c1e26] opacity-60' => ! $trophy['achieved'],
                ])>
                    <div class="flex items-center justify-between gap-2">
                        <p @class(['text-sm font-semibold', 'text-yellow-400' => $trophy['achieved'], 'text-mist-300' => ! $trophy['achieved']])>{{ $trophy['label'] }}</p>
                        @if ($trophy['achieved'])
                            <span class="text-yellow-400" aria-label="Получено">✓</span>
                        @endif
                    </div>
                    <p class="mt-2 text-[11px] text-mist-400">
                        {{ number_format((int) $trophy['value'], 0, '.', ' ') }} / {{ number_format((int) $trophy['target'], 0, '.', ' ') }}
                    </p>
                    <div class="mt-2 h-1.5 w-full bg-ink-900/60">
                        @php $pct = $trophy['target'] > 0 ? min(100, $trophy['value'] / $trophy['target'] * 100) : 0; @endphp
                        <div class="h-full {{ $trophy['achieved'] ? 'bg-yellow-500' : 'bg-mist-500' }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
