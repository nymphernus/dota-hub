@extends('dota.layouts.profile')

@section('title', $pageTitle ?? ($player['nickname'].' - Рекорды - DotaHub'))
@section('description', 'Личные рекорды игрока '.$player['nickname'].' в Dota 2: самый длинный и короткий матч, максимум убийств, урона, GPM и серии побед и поражений.')

@section('profile-content')
    <section class="mt-4" x-data="{ tab: 'all' }">
        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <div>
                <h2 class="section-heading">Рекорды</h2>
                <p class="mt-0.5 text-[11px] text-mist-400">Лучшие результаты игрока в отдельных матчах</p>
            </div>
            <span class="badge text-mist-400" x-text="tab === 'ranked' ? '{{ count($rankedRecords) }} достижений' : '{{ count($records) }} достижений'"></span>
        </div>

        <div class="mt-3 inline-flex rounded-none bg-ink-900/40 p-1" role="tablist" aria-label="Тип рекордов">
            <button
                type="button"
                role="tab"
                aria-controls="records-all"
                :aria-selected="(tab === 'all').toString()"
                @click="tab = 'all'"
                class="rounded-none px-3 py-2 text-sm font-semibold transition"
                :class="tab === 'all' ? 'bg-accent-400/15 text-accent-300' : 'text-mist-400 hover:text-mist-200'"
            >Все матчи</button>
            <button
                type="button"
                role="tab"
                aria-controls="records-ranked"
                :aria-selected="(tab === 'ranked').toString()"
                @click="tab = 'ranked'"
                class="rounded-none px-3 py-2 text-sm font-semibold transition"
                :class="tab === 'ranked' ? 'bg-accent-400/15 text-accent-300' : 'text-mist-400 hover:text-mist-200'"
            >Рейтинг</button>
        </div>

        <div id="records-all" role="tabpanel" x-show="tab === 'all'" x-cloak>
            @include('dota.partials.records-grid', ['records' => $records])
        </div>

        <div id="records-ranked" role="tabpanel" x-show="tab === 'ranked'" x-cloak>
            @include('dota.partials.records-grid', ['records' => $rankedRecords])
        </div>
    </section>
@endsection
