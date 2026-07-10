<x-app-layout>
    <div
        class="space-y-6"
        x-data="developerToolkit(@js($formState), @js($examples), @js($providerSpecs))"
        x-init="init()"
    >
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.08),#ffffff_72%)] shadow-sm">
            <div class="p-6 sm:p-8">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <div class="brand-badge bg-cyan-100 text-cyan-800">Developer only</div>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Business Scenario Simulator
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm text-slate-600 sm:text-base">
                            Local business day simulator for QA, demonstrations and operational testing. It reuses the real ingestion path and stays hidden outside local or debug environments.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-3xl border border-white/80 bg-white/85 px-4 py-3 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Provider</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy" x-text="providerLabel()"></div>
                        </div>
                        <div class="rounded-3xl border border-white/80 bg-white/85 px-4 py-3 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Phone Number ID</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $connection->provider_phone_number_id }}</div>
                        </div>
                        <div class="rounded-3xl border border-white/80 bg-white/85 px-4 py-3 shadow-sm backdrop-blur">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Mode</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">Local / Debug</div>
                        </div>
                    </div>
                </div>

                @if ($result)
                    <div class="mt-6 rounded-[1.75rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-950 shadow-sm">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="max-w-2xl">
                                <div class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700">Result</div>
                                <div class="mt-1 text-base font-medium">{{ $result['message'] }}</div>

                                @if (! empty($result['execution_ms']))
                                    <div class="mt-4">
                                        <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                                            <span>Execution</span>
                                            <span>{{ $result['execution_ms'] }} ms</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-emerald-100">
                                            <div class="h-full w-full rounded-full bg-emerald-500"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:w-[640px] xl:grid-cols-3">
                            <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Orders</div>
                                <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['orders_created'] ?? $result['generated_orders'] ?? $result['processed_count'] ?? 0 }}</div>
                            </div>
                            <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Customers</div>
                                <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['customers_created'] ?? $result['generated_customers'] ?? 0 }}</div>
                            </div>
                            <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">WhatsApp</div>
                                <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['whatsapp_orders'] ?? $result['whatsapp_count'] ?? 0 }}</div>
                            </div>
                            <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Telegram</div>
                                <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['telegram_orders'] ?? $result['telegram_count'] ?? 0 }}</div>
                            </div>
                            <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Duplicates</div>
                                <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['duplicate_orders'] ?? $result['duplicate_count'] ?? $result['ignored_count'] ?? 0 }}</div>
                            </div>
                            <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">VIP / recurrent</div>
                                <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['vip_customers'] ?? $result['vip_count'] ?? 0 }}</div>
                            </div>
                        </div>
                        @if (! empty($result['metrics']) && is_array($result['metrics']))
                            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Customers</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['metrics']['customers'] ?? 0 }}</div>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Delivery / Pickup</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ ($result['metrics']['delivery'] ?? 0) }} / {{ ($result['metrics']['pickup'] ?? 0) }}</div>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Today / Tomorrow</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ ($result['metrics']['today'] ?? 0) }} / {{ ($result['metrics']['tomorrow'] ?? 0) }}</div>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Urgent / Duplicates</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ ($result['metrics']['urgent'] ?? 0) }} / {{ ($result['metrics']['duplicates'] ?? 0) }}</div>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Avg parser confidence</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['metrics']['average_parser_confidence'] !== null ? number_format((float) $result['metrics']['average_parser_confidence'], 2) : '—' }}</div>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Avg priority score</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['metrics']['average_priority_score'] !== null ? number_format((float) $result['metrics']['average_priority_score'], 2) : '—' }}</div>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Avg SLA</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['metrics']['average_sla'] !== null ? number_format((float) $result['metrics']['average_sla'], 2) : '—' }}</div>
                                </div>
                                <div class="rounded-2xl bg-white/80 px-4 py-3 shadow-sm">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Execution time</div>
                                    <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $result['execution_ms'] ?? 0 }} ms</div>
                                </div>
                            </div>
                        @endif
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3">
                            @if (! empty($result['order_url']))
                                <a href="{{ $result['order_url'] }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                    View latest order
                                </a>
                            @endif
                            @if (! empty($result['incoming_message_url']))
                                <a href="{{ $result['incoming_message_url'] }}" class="inline-flex items-center justify-center rounded-2xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:border-emerald-400 hover:text-emerald-800">
                                    View latest incoming message
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-6">
                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-brand-navy">Custom Ingestion Playground</h2>
                            <p class="mt-1 text-sm text-slate-600">Switch provider and the payload shape, required fields and examples update automatically.</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <template x-for="field in activeSpec().required_fields" :key="field">
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700" x-text="field"></span>
                            </template>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <template x-for="example in activeExamples()" :key="example.key">
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-brand-primary/30 hover:text-brand-primary"
                                @click="applyExample(example)"
                                x-text="example.label"
                            ></button>
                        </template>
                    </div>

                    <form method="POST" action="{{ route('developer.webhook-simulator.send') }}" class="mt-6 space-y-6">
                        @csrf
                        <input type="hidden" name="payload_source" x-model="form.payload_source">

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">Provider</span>
                                <select name="provider" x-model="form.provider" @change="onProviderChange()" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="telegram">Telegram</option>
                                    <option value="instagram">Instagram</option>
                                </select>
                            </label>

                            <label class="block" x-show="form.provider === 'whatsapp'">
                                <span class="text-sm font-semibold text-slate-700">Phone Number ID</span>
                                <input type="text" name="phone_number_id" x-model="form.phone_number_id" @input="if (form.payload_source === 'fields') syncPreview()" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block" x-show="form.provider !== 'instagram'">
                                <span class="text-sm font-semibold text-slate-700" x-text="fieldLabel('customer_name')"></span>
                                <input type="text" name="customer_name" x-model="form.customer_name" @input="if (form.payload_source === 'fields') syncPreview()" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                            </label>

                            <label class="block" x-show="form.provider !== 'instagram'">
                                <span class="text-sm font-semibold text-slate-700" x-text="fieldLabel('customer_phone')"></span>
                                <input type="text" name="customer_phone" x-model="form.customer_phone" @input="if (form.payload_source === 'fields') syncPreview()" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block" x-show="form.provider !== 'instagram'">
                                <span class="text-sm font-semibold text-slate-700" x-text="fieldLabel('message_id')"></span>
                                <input type="text" name="message_id" x-model="form.message_id" @input="if (form.payload_source === 'fields') syncPreview()" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                            </label>

                            <label class="block" x-show="form.provider !== 'instagram'">
                                <span class="text-sm font-semibold text-slate-700" x-text="fieldLabel('message_text')"></span>
                                <input type="text" name="message_text" x-model="form.message_text" @input="if (form.payload_source === 'fields') syncPreview()" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                            </label>
                        </div>

                        <div x-show="form.provider === 'instagram'" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            Instagram is a placeholder only. The preview still renders a local stub, but no operational ingestion is available yet.
                        </div>

                        <details class="group rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4" open>
                            <summary class="cursor-pointer list-none text-sm font-semibold text-slate-700">
                                Payload preview
                                <span class="ml-2 rounded-full bg-slate-100 px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500" x-text="form.payload_source === 'preview' ? 'Used as-is' : 'Generated from the form'"></span>
                            </summary>
                            <textarea
                                name="payload_preview"
                                x-model="form.payload_preview"
                                @input="form.payload_source = 'preview'"
                                rows="24"
                                class="mt-4 w-full rounded-[1.5rem] border-slate-300 bg-slate-950 px-4 py-4 font-mono text-sm text-slate-100 shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                            ></textarea>
                        </details>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                Send simulated webhook
                            </button>
                            <button type="button" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary" @click="restoreFromForm()">
                                Restore from form
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-brand-navy">Business Scenarios</h2>
                            <p class="mt-1 text-sm text-slate-600">Generate realistic business days through the live ingestion path, then inspect the resulting metrics.</p>
                        </div>

                        <div class="grid gap-2 text-xs text-slate-500 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">Real ingestion</div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">Live queue</div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">Operations agenda</div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        @foreach ($businessScenarios as $scenario)
                            <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}" class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 shadow-sm">
                                @csrf
                                <input type="hidden" name="action" value="business_scenario">
                                <input type="hidden" name="scenario" value="{{ $scenario['key'] }}">

                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-lg font-semibold text-brand-navy">{{ $scenario['label'] }}</div>
                                        <div class="mt-1 text-sm text-slate-600">{{ $scenario['description'] }}</div>
                                    </div>
                                    <button type="submit" class="rounded-2xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                        Generate Day
                                    </button>
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Customers</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['customers'] }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Orders</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['orders'] }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">WhatsApp</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['provider_mix']['whatsapp'] ?? 0 }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Telegram</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['provider_mix']['telegram'] ?? 0 }}</dd>
                                    </div>
                                </dl>
                            </form>
                        @endforeach
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-semibold text-brand-navy">Create Custom Customer Message</h2>
                                <p class="mt-1 text-sm text-slate-600">Inject a single customer message through the same ingestion pipeline used in production.</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                Current provider: <span class="font-semibold text-brand-navy" x-text="providerLabel()"></span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="action" value="business_custom_message">

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-semibold text-slate-700">Customer</span>
                                    <select name="customer_mode" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                        <option value="new">New customer</option>
                                        <option value="existing">Existing customer</option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-semibold text-slate-700">Provider</span>
                                    <select name="provider" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="telegram">Telegram</option>
                                        <option value="instagram">Instagram placeholder</option>
                                    </select>
                                </label>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-semibold text-slate-700">Customer name</span>
                                    <input type="text" name="customer_name" value="{{ $formState['customer_name'] ?? 'Maria Lopez' }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                </label>

                                <label class="block">
                                    <span class="text-sm font-semibold text-slate-700">Customer phone / chat id</span>
                                    <input type="text" name="customer_phone" value="{{ $formState['customer_phone'] ?? '50255510001' }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary">
                                </label>
                            </div>

                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">Message</span>
                                <textarea name="message" rows="4" class="mt-2 w-full rounded-[1.5rem] border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand-primary focus:ring-brand-primary" placeholder="Ocupo 20 bloques para manana temprano. Yo paso por ellos. Pago por SINPE."></textarea>
                            </label>

                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                Inject Message
                            </button>
                        </form>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                            <h2 class="text-xl font-semibold text-brand-navy">Random Message Generator</h2>
                            <p class="mt-1 text-sm text-slate-600">Generate mixed realistic messages for products, delivery, pickup, dates, payments and urgency.</p>

                            <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}" class="mt-5">
                                @csrf
                                <input type="hidden" name="action" value="business_random_messages">
                                <div class="flex flex-wrap gap-3">
                                    @foreach ([10, 25, 50, 100] as $count)
                                        <button type="submit" name="business_count" value="{{ $count }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-brand-primary/30 hover:text-brand-primary">
                                            Generate {{ $count }} Messages
                                        </button>
                                    @endforeach
                                </div>
                            </form>
                        </div>

                        <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                            <h2 class="text-xl font-semibold text-brand-navy">Simulate Business Day</h2>
                            <p class="mt-1 text-sm text-slate-600">Replay a timed sequence that naturally updates the live queue and operations agenda.</p>

                            <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}" class="mt-5">
                                @csrf
                                <input type="hidden" name="action" value="business_day">
                                <div class="flex flex-wrap gap-3">
                                    @foreach ($simulationSpeeds as $speed)
                                        <button type="submit" name="speed" value="{{ $speed }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-brand-primary/30 hover:text-brand-primary">
                                            {{ $speed }}x
                                        </button>
                                    @endforeach
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-navy">Reset</h2>
                    <p class="mt-1 text-sm text-slate-600">Clear only generated simulation data. Real customers and real operations data stay untouched.</p>

                    <div class="mt-5 grid gap-3 lg:grid-cols-3">
                        @foreach ([
                            'today' => 'Today simulation',
                            'demo_customers' => 'Demo customers',
                            'demo_orders' => 'Demo orders',
                            'messages' => 'Messages',
                            'notifications' => 'Notifications',
                            'webhook_logs' => 'Webhook logs',
                        ] as $scope => $label)
                            <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}" onsubmit="return confirm('Reset {{ $label }}?');">
                                @csrf
                                <input type="hidden" name="action" value="business_reset">
                                <input type="hidden" name="business_reset_scope" value="{{ $scope }}">
                                <button type="submit" class="w-full rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 transition hover:border-amber-400 hover:bg-amber-100">
                                    Reset {{ $label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-brand-navy">Legacy Scenario Generator</h2>
                            <p class="mt-1 text-sm text-slate-600">Predefined cards populate the app with realistic operational data in one click.</p>
                        </div>

                        <div class="grid gap-2 text-xs text-slate-500 sm:grid-cols-3">
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">Orders</div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">Customers</div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">Channels / VIP / Duplicates</div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        @foreach ($scenarios as $key => $scenario)
                            <form method="POST" action="{{ $key === 'ferreteria_pequena' ? route('developer.toolkit.scenarios.small-hardware-store') : route('developer.webhook-simulator.generate') }}" class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 shadow-sm">
                                @csrf
                                <input type="hidden" name="action" value="scenario">
                                <input type="hidden" name="scenario" value="{{ $key }}">
                                <input type="hidden" name="provider" x-model="form.provider">

                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-lg font-semibold text-brand-navy">{{ $scenario['label'] }}</div>
                                        <div class="mt-1 text-sm text-slate-600">{{ $scenario['estimated_time'] ?? 'Batch friendly' }}</div>
                                    </div>
                                    @if ($key !== 'ferreteria_pequena')
                                        <button type="submit" class="rounded-2xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                            Generate
                                        </button>
                                    @endif
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Customers</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['customers'] }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Orders</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['orders'] }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">VIP</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['vip'] }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-white px-4 py-3">
                                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Duplicates</dt>
                                        <dd class="mt-1 font-semibold text-brand-navy">{{ $scenario['duplicates'] }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-4 rounded-2xl bg-white px-4 py-3 text-sm text-slate-600">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Channels</div>
                                    <div class="mt-1">{{ implode(' / ', array_map(fn ($provider, $count) => ucfirst($provider) . ' ' . $count, array_keys($scenario['provider_mix'] ?? []), array_values($scenario['provider_mix'] ?? []))) }}</div>
                                </div>

                                @if ($key === 'ferreteria_pequena')
                                    <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                        Este escenario crea datos demo marcados para pruebas y no toca datos reales.
                                    </p>

                                    <div class="mt-4 flex flex-wrap gap-3">
                                        <button type="submit" class="rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                            Generar escenario
                                        </button>
                                        <button type="submit" formaction="{{ route('developer.toolkit.reset-demo-data') }}" formmethod="POST" class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:border-amber-400 hover:bg-amber-100">
                                            Reiniciar datos demo
                                        </button>
                                    </div>
                                @endif
                            </form>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-brand-navy">Legacy Quick Generators</h2>
                            <p class="mt-1 text-sm text-slate-600">Generate a small, medium or large wave of orders using the currently selected provider.</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Current provider: <span class="font-semibold text-brand-navy" x-text="providerLabel()"></span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}" class="mt-5">
                        @csrf
                        <input type="hidden" name="action" value="quick">
                        <input type="hidden" name="provider" x-model="form.provider">
                        <div class="flex flex-wrap gap-3">
                            @foreach ($quickCounts as $count)
                                <button type="submit" name="count" value="{{ $count }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-brand-primary/30 hover:text-brand-primary">
                                    Generate {{ $count }} order{{ $count > 1 ? 's' : '' }}
                                </button>
                            @endforeach
                        </div>
                    </form>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-brand-navy">Legacy Customer Generator</h2>
                        <p class="mt-1 text-sm text-slate-600">Generate customers only using random Costa Rican names and phone numbers.</p>

                        <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="action" value="customers">
                            <input type="hidden" name="provider" x-model="form.provider">
                            <div class="flex flex-wrap gap-3">
                                @foreach ($customerCounts as $count)
                                    <button type="submit" name="customer_count" value="{{ $count }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-brand-primary/30 hover:text-brand-primary">
                                        Generate {{ $count }} customer{{ $count > 1 ? 's' : '' }}
                                    </button>
                                @endforeach
                            </div>
                        </form>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-brand-navy">Legacy Operational QA</h2>
                        <p class="mt-1 text-sm text-slate-600">Fast buttons for duplicate, VIP, parser failure, unknown product, busy day and empty inbox checks.</p>

                        <div class="mt-5 grid gap-3">
                            @foreach ($qaCases as $qaCase)
                                <form method="POST" action="{{ route('developer.webhook-simulator.generate') }}">
                                    @csrf
                                    <input type="hidden" name="action" value="qa">
                                    <input type="hidden" name="qa_case" value="{{ $qaCase['key'] }}">
                                    <input type="hidden" name="provider" x-model="form.provider">
                                    <button type="submit" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:border-brand-primary/30 hover:text-brand-primary">
                                        {{ $qaCase['label'] }}
                                        <span class="mt-1 block text-xs font-normal text-slate-500">{{ $qaCase['description'] }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-navy">Legacy Demo Reset</h2>
                    <p class="mt-1 text-sm text-slate-600">Deletes demo orders or customers only. Admin users and organizations remain untouched.</p>

                    <div class="mt-5 grid gap-3 lg:grid-cols-3">
                        <form method="POST" action="{{ route('developer.webhook-simulator.reset') }}" onsubmit="return confirm('Delete generated demo orders?');">
                            @csrf
                            <input type="hidden" name="scope" value="orders">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="w-full rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 transition hover:border-amber-400 hover:bg-amber-100">
                                Delete generated demo orders
                            </button>
                        </form>

                        <form method="POST" action="{{ route('developer.webhook-simulator.reset') }}" onsubmit="return confirm('Delete generated demo customers?');">
                            @csrf
                            <input type="hidden" name="scope" value="customers">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="w-full rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 transition hover:border-amber-400 hover:bg-amber-100">
                                Delete generated demo customers
                            </button>
                        </form>

                        <form method="POST" action="{{ route('developer.webhook-simulator.reset') }}" onsubmit="return confirm('Reset demo environment?');">
                            @csrf
                            <input type="hidden" name="scope" value="environment">
                            <input type="hidden" name="confirm" value="1">
                            <button type="submit" class="w-full rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900 transition hover:border-rose-400 hover:bg-rose-100">
                                Reset demo environment
                            </button>
                        </form>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-navy">Metrics</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Generated customers</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $metrics['generated_customers'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Generated orders</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $metrics['generated_orders'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">WhatsApp</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $metrics['whatsapp'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Telegram</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $metrics['telegram'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">VIP</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $metrics['vip'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Duplicates</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $metrics['duplicates'] ?? 0 }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-navy">Business Metrics</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Customers</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['customers'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Orders</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['orders'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Delivery</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['delivery'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Pickup</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['pickup'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Today</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['today'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Tomorrow</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['tomorrow'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Urgent</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['urgent'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">VIP</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['vip'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Duplicates</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['duplicates'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Avg parser confidence</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['average_parser_confidence'] !== null ? number_format((float) $businessMetrics['average_parser_confidence'], 2) : '—' }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Avg priority score</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['average_priority_score'] !== null ? number_format((float) $businessMetrics['average_priority_score'], 2) : '—' }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Avg SLA</div>
                            <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $businessMetrics['average_sla'] !== null ? number_format((float) $businessMetrics['average_sla'], 2) : '—' }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-navy">Provider Details</h2>
                    <div class="mt-4 space-y-3">
                        <template x-for="item in activeSpec().examples" :key="item">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600" x-text="item"></div>
                        </template>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-brand-navy">Connection</h2>
                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Status</dt>
                            <dd class="mt-1 text-sm font-medium text-brand-navy">{{ $connection->status }}</dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Provider status</dt>
                            <dd class="mt-1 text-sm font-medium text-brand-navy">{{ $connection->provider_status ?? 'draft' }}</dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Organization</dt>
                            <dd class="mt-1 text-sm font-medium text-brand-navy">{{ $connection->organization_id }}</dd>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Channel</dt>
                            <dd class="mt-1 text-sm font-medium text-brand-navy">{{ $connection->channel }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('developerToolkit', (initialForm, examples, providerSpecs) => ({
                form: JSON.parse(JSON.stringify(initialForm)),
                examples,
                providerSpecs,
                init() {
                    this.syncPreview();
                },
                activeSpec() {
                    return this.providerSpecs[this.form.provider] || this.providerSpecs.whatsapp;
                },
                providerLabel() {
                    return this.activeSpec().label || 'WhatsApp';
                },
                fieldLabel(field) {
                    const labels = {
                        whatsapp: {
                            customer_name: 'Customer name',
                            customer_phone: 'Customer phone',
                            message_id: 'Message id',
                            message_text: 'Message text',
                        },
                        telegram: {
                            customer_name: 'Sender name',
                            customer_phone: 'Chat ID',
                            message_id: 'Update id',
                            message_text: 'Message text',
                        },
                        instagram: {
                            customer_name: 'Sender name',
                            customer_phone: 'Conversation id',
                            message_id: 'Message id',
                            message_text: 'Message text',
                        },
                    };

                    return labels[this.form.provider]?.[field] || field;
                },
                onProviderChange() {
                    if (this.form.provider === 'instagram') {
                        this.form.payload_source = 'preview';
                    }

                    this.syncPreview();
                },
                activeExamples() {
                    return this.examples[this.form.provider] || [];
                },
                applyExample(example) {
                    if (example.form) {
                        Object.entries(example.form).forEach(([key, value]) => {
                            this.form[key] = value;
                        });
                    }

                    this.form.provider = example.form?.provider || this.form.provider;
                    this.form.payload_source = example.payload_source || 'fields';

                    if (example.payload_preview) {
                        this.form.payload_preview = example.payload_preview;
                        return;
                    }

                    this.syncPreview();
                },
                restoreFromForm() {
                    this.form.payload_source = 'fields';
                    this.syncPreview();
                },
                syncPreview() {
                    if (this.form.provider === 'instagram') {
                        this.form.payload_preview = JSON.stringify({
                            object: 'instagram',
                            status: 'coming_soon',
                        }, null, 2);
                        return;
                    }

                    if (this.form.payload_source !== 'fields') {
                        return;
                    }

                    this.form.payload_preview = JSON.stringify(this.buildPayloadFromFields(), null, 2);
                },
                buildPayloadFromFields() {
                    if (this.form.provider === 'telegram') {
                        const updateId = this.stableTelegramUpdateId(this.form.message_id || '9010001');
                        const chatId = this.telegramChatId(this.form.customer_phone || '4001');

                        return {
                            update_id: updateId,
                            message: {
                                message_id: updateId + 100,
                                date: Math.floor(Date.now() / 1000),
                                chat: {
                                    id: chatId,
                                    type: 'private',
                                },
                                from: {
                                    id: chatId,
                                    username: this.telegramUsername(this.form.customer_name || 'Maria Lopez'),
                                    first_name: this.form.customer_name || 'Maria Lopez',
                                },
                                text: this.form.message_text || '',
                            },
                        };
                    }

                    return {
                        object: 'whatsapp_business_account',
                        entry: [
                            {
                                id: 'entry-simulator-1',
                                changes: [
                                    {
                                        field: 'messages',
                                        value: {
                                            messaging_product: 'whatsapp',
                                            metadata: {
                                                display_phone_number: '+50255550000',
                                                phone_number_id: this.form.phone_number_id,
                                            },
                                            contacts: [
                                                {
                                                    profile: {
                                                        name: this.form.customer_name || null,
                                                    },
                                                    wa_id: this.form.customer_phone || null,
                                                },
                                            ],
                                            messages: [
                                                {
                                                    from: this.form.customer_phone || null,
                                                    id: this.form.message_id || null,
                                                    timestamp: Math.floor(Date.now() / 1000).toString(),
                                                    type: 'text',
                                                    text: {
                                                        body: this.form.message_text || '',
                                                    },
                                                },
                                            ],
                                        },
                                    },
                                ],
                            },
                        ],
                    };
                },
                telegramChatId(seed) {
                    return String(seed || '').replace(/\D+/g, '') || '4001';
                },
                stableTelegramUpdateId(seed) {
                    const digits = String(seed || '').replace(/\D+/g, '');

                    if (digits) {
                        return parseInt((digits + '000000').slice(0, 9), 10);
                    }

                    let hash = 0;

                    for (let i = 0; i < String(seed || '').length; i++) {
                        hash = ((hash << 5) - hash) + String(seed || '').charCodeAt(i);
                        hash |= 0;
                    }

                    return Math.abs(hash);
                },
                telegramUsername(name) {
                    const value = String(name || '')
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '');

                    return value ? `${value}_cr` : null;
                },
            }));
        });
    </script>
</x-app-layout>
