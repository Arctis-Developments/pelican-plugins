<x-filament-panels::page>
    @php($profile = $this->profile)

    <style>
        .pc-player-profile {
            display: grid;
            gap: 1.5rem;
            color: #111827;
        }

        .pc-player-profile__stats,
        .pc-player-profile__stack {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .pc-player-profile__content {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .pc-player-profile__card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .pc-player-profile__stat {
            padding: 1rem 1.125rem;
            min-height: 8.75rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .pc-player-profile__label {
            font-size: 0.76rem;
            color: #6b7280;
        }

        .pc-player-profile__value {
            margin-top: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .pc-player-profile__value--success {
            color: #059669;
        }

        .pc-player-profile__header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .pc-player-profile__heading {
            font-size: 0.95rem;
            font-weight: 700;
        }

        .pc-player-profile__subheading {
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .pc-player-profile__body {
            padding: 1.5rem;
        }

        .pc-player-profile__meta {
            display: grid;
            gap: 1.25rem;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .pc-player-profile__full {
            grid-column: 1 / -1;
        }

        .pc-player-profile__meta-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .pc-player-profile__meta-value {
            margin-top: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .pc-player-profile__meta-value--mono,
        .pc-player-profile__code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            word-break: break-all;
        }

        .pc-player-profile__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .pc-player-profile__chip,
        .pc-player-profile__badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .pc-player-profile__chip {
            background: #f3f4f6;
            color: #374151;
        }

        .pc-player-profile__badge {
            background: #f3f4f6;
            color: #111827;
        }

        .pc-player-profile__badge--join {
            background: #dcfce7;
            color: #166534;
        }

        .pc-player-profile__badge--leave {
            background: #e5e7eb;
            color: #374151;
        }

        .pc-player-profile__badge--chat {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .pc-player-profile__badge--command {
            background: #fef3c7;
            color: #b45309;
        }

        .pc-player-profile__badge--uuid {
            background: #ede9fe;
            color: #6d28d9;
        }

        .pc-player-profile__list {
            display: grid;
            gap: 0.75rem;
        }

        .pc-player-profile__list-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 0.875rem;
            padding: 1rem;
        }

        .pc-player-profile__list-row {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .pc-player-profile__muted {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .pc-player-profile__stat .pc-player-profile__muted {
            font-size: 0.78rem;
            line-height: 1.3;
        }

        .pc-player-profile__table-wrap {
            overflow-x: auto;
        }

        .pc-player-profile__table-wrap--events {
            max-height: 32rem;
        }

        .pc-player-profile__table {
            width: 100%;
            border-collapse: collapse;
        }

        .pc-player-profile__table thead {
            background: #f8fafc;
        }

        .pc-player-profile__table th,
        .pc-player-profile__table td {
            padding: 0.9rem 1rem;
            text-align: left;
            vertical-align: top;
            border-top: 1px solid #e5e7eb;
            font-size: 0.92rem;
        }

        .pc-player-profile__table th {
            border-top: none;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
        }

        .pc-player-profile__pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .pc-player-profile__pager-button {
            appearance: none;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            border-radius: 0.75rem;
            padding: 0.55rem 0.9rem;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
        }

        .pc-player-profile__pager-button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        @media (min-width: 768px) {
            .pc-player-profile__stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .pc-player-profile__meta {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .pc-player-profile__stats {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }

            .pc-player-profile__content {
                grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            }

            .pc-player-profile__stack {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .dark .pc-player-profile {
            color: #f9fafb;
        }

        .dark .pc-player-profile__card {
            background: #18181b;
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: none;
        }

        .dark .pc-player-profile__label,
        .dark .pc-player-profile__subheading,
        .dark .pc-player-profile__meta-label,
        .dark .pc-player-profile__muted,
        .dark .pc-player-profile__table th {
            color: #a1a1aa;
        }

        .dark .pc-player-profile__header,
        .dark .pc-player-profile__table th,
        .dark .pc-player-profile__table td,
        .dark .pc-player-profile__list-item,
        .dark .pc-player-profile__pager {
            border-color: rgba(255, 255, 255, 0.08);
        }

        .dark .pc-player-profile__list-item,
        .dark .pc-player-profile__table thead {
            background: rgba(255, 255, 255, 0.03);
        }

        .dark .pc-player-profile__chip,
        .dark .pc-player-profile__badge {
            background: rgba(255, 255, 255, 0.08);
            color: #f4f4f5;
        }

        .dark .pc-player-profile__badge--join {
            background: rgba(16, 185, 129, 0.18);
            color: #6ee7b7;
        }

        .dark .pc-player-profile__badge--leave {
            background: rgba(255, 255, 255, 0.08);
            color: #d4d4d8;
        }

        .dark .pc-player-profile__badge--chat {
            background: rgba(59, 130, 246, 0.18);
            color: #93c5fd;
        }

        .dark .pc-player-profile__badge--command {
            background: rgba(245, 158, 11, 0.18);
            color: #fcd34d;
        }

        .dark .pc-player-profile__badge--uuid {
            background: rgba(139, 92, 246, 0.18);
            color: #c4b5fd;
        }

        .dark .pc-player-profile__pager-button {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f4f4f5;
        }
    </style>

    <div class="pc-player-profile" wire:poll.15s>
        <div class="pc-player-profile__stats">
            <div class="pc-player-profile__card pc-player-profile__stat">
                <p class="pc-player-profile__label">{{ trans('player-counter::query.total_logins') }}</p>
                <p class="pc-player-profile__value">{{ $profile['total_logins'] }}</p>
            </div>

            <div class="pc-player-profile__card pc-player-profile__stat">
                <p class="pc-player-profile__label">{{ trans('player-counter::query.total_playtime') }}</p>
                <p class="pc-player-profile__value">{{ $profile['total_playtime'] }}</p>
            </div>

            <div class="pc-player-profile__card pc-player-profile__stat">
                <p class="pc-player-profile__label">{{ trans('player-counter::query.unique_ips') }}</p>
                <p class="pc-player-profile__value">{{ $profile['unique_ip_count'] }}</p>
            </div>

            <div class="pc-player-profile__card pc-player-profile__stat">
                <p class="pc-player-profile__label">{{ trans('player-counter::query.messages') }}</p>
                <p class="pc-player-profile__value">{{ $profile['message_count'] }}</p>
            </div>

            <div class="pc-player-profile__card pc-player-profile__stat">
                <p class="pc-player-profile__label">{{ trans('player-counter::query.commands') }}</p>
                <p class="pc-player-profile__value">{{ $profile['command_count'] }}</p>
            </div>

            <div class="pc-player-profile__card pc-player-profile__stat">
                <p class="pc-player-profile__label">{{ trans('player-counter::query.current_session') }}</p>

                @if ($profile['current_session'])
                    <p class="pc-player-profile__value pc-player-profile__value--success">
                        {{ $this->formatDuration(null, $profile['current_session']['joined_at']) }}
                    </p>
                    <p class="pc-player-profile__muted" style="margin-top: auto;">
                        {{ trans('player-counter::query.joined_at') }}:
                        {{ $this->formatDateTime($profile['current_session']['joined_at']) }}
                    </p>
                @else
                    <p class="pc-player-profile__value">
                        {{ trans('player-counter::query.no_active_session') }}
                    </p>
                    <p class="pc-player-profile__muted" style="margin-top: auto;">
                        {{ trans('player-counter::query.last_seen') }}:
                        {{ $this->formatDateTime($profile['last_seen']) }}
                    </p>
                @endif
            </div>
        </div>

        <div class="pc-player-profile__content">
            <section class="pc-player-profile__card">
                <div class="pc-player-profile__header">
                    <h2 class="pc-player-profile__heading">{{ trans('player-counter::query.details') }}</h2>
                    <p class="pc-player-profile__subheading">{{ trans('player-counter::query.player_profile') }}</p>
                </div>

                <div class="pc-player-profile__body">
                    <div class="pc-player-profile__meta">
                        <div>
                            <p class="pc-player-profile__meta-label">{{ trans('player-counter::query.player_id') }}</p>
                            <p class="pc-player-profile__meta-value pc-player-profile__meta-value--mono">{{ $profile['player_source_id'] ?? trans('player-counter::query.unknown') }}</p>
                        </div>

                        <div>
                            <p class="pc-player-profile__meta-label">{{ trans('player-counter::query.total_sessions') }}</p>
                            <p class="pc-player-profile__meta-value">{{ $profile['total_sessions'] }}</p>
                        </div>

                        <div>
                            <p class="pc-player-profile__meta-label">{{ trans('player-counter::query.first_seen') }}</p>
                            <p class="pc-player-profile__meta-value">{{ $this->formatDateTime($profile['first_seen']) }}</p>
                        </div>

                        <div>
                            <p class="pc-player-profile__meta-label">{{ trans('player-counter::query.last_seen') }}</p>
                            <p class="pc-player-profile__meta-value">{{ $this->formatDateTime($profile['last_seen']) }}</p>
                        </div>

                        <div class="pc-player-profile__full">
                            <p class="pc-player-profile__meta-label">{{ trans('player-counter::query.aliases') }}</p>

                            <div class="pc-player-profile__chips">
                                @forelse ($profile['aliases'] as $alias)
                                    <span class="pc-player-profile__chip">{{ $alias }}</span>
                                @empty
                                    <span class="pc-player-profile__muted">{{ trans('player-counter::query.unknown') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="pc-player-profile__card">
                <div class="pc-player-profile__header">
                    <h2 class="pc-player-profile__heading">{{ trans('player-counter::query.ip_history') }}</h2>
                    <p class="pc-player-profile__subheading">{{ trans('player-counter::query.unique_ips') }}: {{ $profile['unique_ip_count'] }}</p>
                </div>

                <div class="pc-player-profile__body">
                    @if (count($profile['ip_addresses']) === 0)
                        <p class="pc-player-profile__muted">{{ trans('player-counter::query.no_ip_history') }}</p>
                    @else
                        <div class="pc-player-profile__list">
                            @foreach ($profile['ip_addresses'] as $ipAddress)
                                <div class="pc-player-profile__list-item">
                                    <div class="pc-player-profile__list-row">
                                        <p class="pc-player-profile__meta-value pc-player-profile__code">{{ $ipAddress['ip_address'] }}</p>
                                        <span class="pc-player-profile__muted">{{ trans('player-counter::query.occurrences') }}: {{ $ipAddress['occurrences'] }}</span>
                                    </div>

                                    <p class="pc-player-profile__muted" style="margin-top: 0.75rem;">
                                        {{ trans('player-counter::query.first_seen') }}: {{ $this->formatDateTime($ipAddress['first_seen_at']) }}
                                    </p>
                                    <p class="pc-player-profile__muted" style="margin-top: 0.25rem;">
                                        {{ trans('player-counter::query.last_seen') }}: {{ $this->formatDateTime($ipAddress['last_seen_at']) }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="pc-player-profile__stack">
            <section class="pc-player-profile__card">
                <div class="pc-player-profile__header">
                    <h2 class="pc-player-profile__heading">{{ trans('player-counter::query.recent_sessions') }}</h2>
                    <p class="pc-player-profile__subheading">{{ trans('player-counter::query.total_sessions') }}: {{ $profile['total_sessions'] }}</p>
                </div>

                <div class="pc-player-profile__table-wrap">
                    @if (count($profile['sessions']) === 0)
                        <p class="pc-player-profile__body pc-player-profile__muted">{{ trans('player-counter::query.no_sessions') }}</p>
                    @else
                        <table class="pc-player-profile__table">
                            <thead>
                                <tr>
                                    <th>{{ trans('player-counter::query.joined_at') }}</th>
                                    <th>{{ trans('player-counter::query.left_at') }}</th>
                                    <th>{{ trans('player-counter::query.session_duration') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($profile['sessions'] as $session)
                                    <tr>
                                        <td>{{ $this->formatDateTime($session['joined_at']) }}</td>
                                        <td>{{ $session['left_at'] ? $this->formatDateTime($session['left_at']) : trans('player-counter::query.online') }}</td>
                                        <td>{{ $this->formatDuration($session['duration_seconds'], $session['joined_at']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="pc-player-profile__card">
                @php($visibleEvents = $this->paginatedEvents())
                <div class="pc-player-profile__header">
                    <h2 class="pc-player-profile__heading">{{ trans('player-counter::query.recent_events') }}</h2>
                    <p class="pc-player-profile__subheading">{{ trans('player-counter::query.commands') }}: {{ $profile['command_count'] }} | {{ trans('player-counter::query.messages') }}: {{ $profile['message_count'] }}</p>
                </div>

                <div class="pc-player-profile__table-wrap pc-player-profile__table-wrap--events">
                    @if (count($profile['events']) === 0)
                        <p class="pc-player-profile__body pc-player-profile__muted">{{ trans('player-counter::query.no_events') }}</p>
                    @else
                        <table class="pc-player-profile__table">
                            <thead>
                                <tr>
                                    <th>{{ trans('player-counter::query.event_type') }}</th>
                                    <th>{{ trans('player-counter::query.event') }}</th>
                                    <th>{{ trans('player-counter::query.last_seen') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visibleEvents as $event)
                                    <tr>
                                        <td>
                                            <span class="pc-player-profile__badge pc-player-profile__badge--{{ $event['event_type'] }}">
                                                {{ $this->eventTypeLabel($event['event_type']) }}
                                            </span>
                                        </td>
                                        <td>
                                            <p>{{ $event['message'] ?? trans('player-counter::query.unknown') }}</p>

                                            @if ($event['ip_address'])
                                                <p class="pc-player-profile__muted pc-player-profile__code" style="margin-top: 0.35rem;">{{ $event['ip_address'] }}</p>
                                            @endif
                                        </td>
                                        <td>{{ $this->formatDateTime($event['occurred_at']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                @if (count($profile['events']) > $this->eventsPerPage)
                    <div class="pc-player-profile__pager">
                        <button class="pc-player-profile__pager-button" type="button" wire:click="previousEventsPage" @disabled($this->eventsPage <= 1)>
                            {{ trans('player-counter::query.previous') }}
                        </button>

                        <span class="pc-player-profile__muted">
                            {{ trans('player-counter::query.page') }} {{ $this->eventsPage }} {{ trans('player-counter::query.of') }} {{ $this->eventsLastPage() }}
                        </span>

                        <button class="pc-player-profile__pager-button" type="button" wire:click="nextEventsPage" @disabled($this->eventsPage >= $this->eventsLastPage())>
                            {{ trans('player-counter::query.next') }}
                        </button>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
