<x-filament-panels::page>
    @php
        $data = $this->getReportData();
    @endphp

    <style>
        /* Base Reset & SVG sizing */
        svg {
            max-width: 100%;
            height: auto;
        }
        .report-icon-sm {
            width: 20px !important;
            height: 20px !important;
            min-width: 20px !important;
        }
        .report-icon-lg {
            width: 26px !important;
            height: 26px !important;
            min-width: 26px !important;
        }

        /* Light Mode Styles */
        .rp-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -2px rgba(0, 0, 0, 0.03);
            border-radius: 16px;
            padding: 24px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .rp-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
        }
        .rp-text-title { color: #0f172a; }
        .rp-text-muted { color: #64748b; }
        .rp-table-header { background-color: #f8fafc; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .rp-table-row { border-bottom: 1px solid #f1f5f9; }
        .rp-table-row:hover { background-color: #f8fafc; }
        .rp-filter-bar {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
        }
        .rp-select {
            background-color: #ffffff;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }

        /* Dark Mode Overrides */
        .dark .rp-card {
            background-color: #1e293b;
            border-color: #334155;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }
        .dark .rp-text-title { color: #f8fafc; }
        .dark .rp-text-muted { color: #94a3b8; }
        .dark .rp-table-header { background-color: #0f172a; color: #cbd5e1; border-bottom-color: #334155; }
        .dark .rp-table-row { border-bottom-color: #334155; }
        .dark .rp-table-row:hover { background-color: rgba(51, 65, 85, 0.4); }
        .dark .rp-filter-bar {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-color: #334155;
        }
        .dark .rp-select {
            background-color: #0f172a;
            color: #f8fafc;
            border-color: #475569;
        }

        /* Print Styles: Force clean light paper mode for PDF/Printer */
        @media print {
            @page {
                margin: 12mm;
                size: auto;
            }

            * {
                color: #0f172a !important;
                background-color: transparent !important;
                box-shadow: none !important;
                text-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html, body, .fi-main-ctn, .fi-main {
                background-color: #ffffff !important;
                background: #ffffff !important;
                color: #0f172a !important;
            }

            .fi-sidebar, 
            .fi-topbar, 
            .fi-header, 
            .no-print,
            .fi-page-header,
            .fi-page-actions {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            .fi-main {
                padding: 0 !important;
                margin: 0 !important;
            }

            .rp-card {
                border: 1px solid #cbd5e1 !important;
                background-color: #ffffff !important;
                color: #0f172a !important;
                margin-bottom: 15px !important;
                border-radius: 8px !important;
                padding: 20px !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            table th {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
                border: 1px solid #cbd5e1 !important;
                font-weight: bold !important;
            }

            table td {
                border: 1px solid #e2e8f0 !important;
                color: #0f172a !important;
            }
        }
        @media screen {
            .print-only {
                display: none !important;
            }
        }
    </style>

    <!-- Print Header (Visible ONLY when printing) -->
    <div class="print-only" style="margin-bottom: 20px; text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 15px;">
        <h1 style="font-size: 24px; font-weight: 800; margin-bottom: 5px; color: #0f172a;">تقرير أداء المبيعات والنشاط الشهري — Elwaly</h1>
        <p style="font-size: 14px; color: #475569;">فترة التقرير: {{ $data['period_label'] }} | تاريخ الطباعة: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <!-- Filter Bar & Active Period Banner -->
    <div class="no-print rp-card rp-filter-bar" style="padding: 16px 24px; margin-bottom: 24px;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px;">
            <!-- Select Filter -->
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="text-sm font-semibold rp-text-title">اختر الفترة الزمنية:</span>
                <select wire:model.live="period" style="padding: 8px 14px; border-radius: 10px; font-size: 14px; font-weight: 500;" class="rp-select">
                    <option value="this_month">الشهر الحالي</option>
                    <option value="today">اليوم</option>
                    <option value="this_week">هذا الأسبوع</option>
                    <option value="custom">نطاق تاريخ مخصص</option>
                </select>

                @if($period === 'custom')
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="date" wire:model.live="dateFrom" style="padding: 6px 12px; border-radius: 8px; font-size: 13px;" class="rp-select" />
                        <span class="text-xs rp-text-muted">إلى</span>
                        <input type="date" wire:model.live="dateUntil" style="padding: 6px 12px; border-radius: 8px; font-size: 13px;" class="rp-select" />
                    </div>
                @endif
            </div>

            <!-- Active Period Badge -->
            <div style="display: flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 20px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2);">
                <x-heroicon-o-calendar class="report-icon-sm" style="color: #3b82f6;" />
                <span style="font-size: 13px; font-weight: 700; color: #2563eb;">{{ $data['period_label'] }}</span>
            </div>
        </div>
    </div>

    <!-- 5 KPI Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 25px;">
        <!-- Card 1: New Customers -->
        <div class="rp-card" style="padding: 20px 24px; display: flex; align-items: center; gap: 16px;">
            <div style="padding: 12px; border-radius: 14px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.25)); color: #2563eb;">
                <x-heroicon-o-user-plus class="report-icon-lg" />
            </div>
            <div>
                <div class="text-xs font-semibold rp-text-muted">العملاء الجدد</div>
                <div class="text-2xl font-extrabold rp-text-title" style="margin-top: 2px;">{{ number_format($data['total_new_customers']) }}</div>
            </div>
        </div>

        <!-- Card 2: Total Activities -->
        <div class="rp-card" style="padding: 20px 24px; display: flex; align-items: center; gap: 16px;">
            <div style="padding: 12px; border-radius: 14px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.25)); color: #059669;">
                <x-heroicon-o-chat-bubble-left-right class="report-icon-lg" />
            </div>
            <div>
                <div class="text-xs font-semibold rp-text-muted">المتابعات والأنشطة</div>
                <div class="text-2xl font-extrabold rp-text-title" style="margin-top: 2px;">{{ number_format($data['total_activities']) }}</div>
            </div>
        </div>

        <!-- Card 3: Won Deals -->
        <div class="rp-card" style="padding: 20px 24px; display: flex; align-items: center; gap: 16px;">
            <div style="padding: 12px; border-radius: 14px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.25)); color: #d97706;">
                <x-heroicon-o-check-badge class="report-icon-lg" />
            </div>
            <div>
                <div class="text-xs font-semibold rp-text-muted">المبيعات المغلقة</div>
                <div class="text-2xl font-extrabold rp-text-title" style="margin-top: 2px;">{{ number_format($data['won_customers_count']) }}</div>
            </div>
        </div>

        <!-- Card 4: Conversion Rate -->
        <div class="rp-card" style="padding: 20px 24px; display: flex; align-items: center; gap: 16px;">
            <div style="padding: 12px; border-radius: 14px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(79, 70, 229, 0.25)); color: #4f46e5;">
                <x-heroicon-o-chart-pie class="report-icon-lg" />
            </div>
            <div>
                <div class="text-xs font-semibold rp-text-muted">معدل التحويل</div>
                <div class="text-2xl font-extrabold" style="color: #4f46e5; margin-top: 2px;">{{ $data['overall_conversion_rate'] }}%</div>
            </div>
        </div>

        <!-- Card 5: Avg Response Time -->
        <div class="rp-card" style="padding: 20px 24px; display: flex; align-items: center; gap: 16px;">
            <div style="padding: 12px; border-radius: 14px; background: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(147, 51, 234, 0.25)); color: #9333ea;">
                <x-heroicon-o-clock class="report-icon-lg" />
            </div>
            <div>
                <div class="text-xs font-semibold rp-text-muted">أول استجابة</div>
                <div class="text-2xl font-extrabold rp-text-title" style="margin-top: 2px;">{{ $data['avg_response_hours'] }} <span style="font-size: 12px; font-weight: normal;" class="rp-text-muted">ساعة</span></div>
            </div>
        </div>
    </div>

    <!-- Sales Leaderboard Table Card -->
    <div class="rp-card mb-6" style="padding: 0; overflow: hidden;">
        <div style="padding: 18px 24px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); display: flex; align-items: center; gap: 10px;">
            <x-heroicon-o-trophy class="report-icon-sm" style="color: #f59e0b;" />
            <h3 class="font-bold rp-text-title" style="font-size: 16px;">ترتيب وأداء مندوبي المبيعات (Leaderboard)</h3>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: right; font-size: 14px;">
                <thead>
                    <tr class="rp-table-header">
                        <th style="padding: 14px 20px; text-align: center; width: 90px;">الترتيب</th>
                        <th style="padding: 14px 20px;">اسم المندوب</th>
                        <th style="padding: 14px 20px; text-align: center;">العملاء المسندون</th>
                        <th style="padding: 14px 20px; text-align: center;">الأنشطة والمكالمات</th>
                        <th style="padding: 14px 20px; text-align: center;">المبيعات المغلقة</th>
                        <th style="padding: 14px 20px; text-align: center;">معدل التحويل (%)</th>
                        <th style="padding: 14px 20px; text-align: center;">متوسط أول استجابة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['sales_performance'] as $index => $rep)
                        <tr class="rp-table-row">
                            <td style="padding: 14px 20px; text-align: center;">
                                @if($index === 0)
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #78350f; font-weight: 800; font-size: 12px; border: 1px solid #fcd34d;">🥇 #1</span>
                                @elseif($index === 1)
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #334155; font-weight: 800; font-size: 12px; border: 1px solid #cbd5e1;">🥈 #2</span>
                                @elseif($index === 2)
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: linear-gradient(135deg, #ffedd5, #fed7aa); color: #7c2d12; font-weight: 800; font-size: 12px; border: 1px solid #fdba74;">🥉 #3</span>
                                @else
                                    <span style="font-weight: 600;" class="rp-text-muted">#{{ $index + 1 }}</span>
                                @endif
                            </td>
                            <td style="padding: 14px 20px; font-weight: 700;" class="rp-text-title">{{ $rep['name'] }}</td>
                            <td style="padding: 14px 20px; text-align: center;" class="rp-text-title">{{ number_format($rep['assigned_count']) }}</td>
                            <td style="padding: 14px 20px; text-align: center; font-weight: 600; color: #059669;">{{ number_format($rep['activities_count']) }}</td>
                            <td style="padding: 14px 20px; text-align: center; font-weight: 800; color: #d97706;">{{ number_format($rep['won_count']) }}</td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: rgba(99, 102, 241, 0.12); color: #4338ca; font-weight: 700; font-size: 12px;">
                                    {{ $rep['conversion_rate'] }}%
                                </span>
                            </td>
                            <td style="padding: 14px 20px; text-align: center;" class="rp-text-muted">{{ $rep['avg_response_hours'] }} ساعة</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 25px; text-align: center;" class="rp-text-muted">لا توجد بيانات أداء متاحة للفترة المحددة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Distributions Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <!-- Platforms Breakdown Card -->
        <div class="rp-card" style="padding: 24px;">
            <h3 class="font-bold rp-text-title" style="font-size: 15px; margin-bottom: 16px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); padding-bottom: 10px;">توزيع العملاء الجدد حسب المنصة / المصدر</h3>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                @forelse($data['platforms_breakdown'] as $item)
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;" class="rp-text-title">
                            <span>{{ $item['name'] }}</span>
                            <span class="rp-text-muted">{{ $item['count'] }} عميل ({{ $item['percentage'] }}%)</span>
                        </div>
                        <div style="width: 100%; height: 9px; border-radius: 6px; background: rgba(148, 163, 184, 0.15); overflow: hidden;">
                            <div style="height: 100%; border-radius: 6px; background: linear-gradient(90deg, #3b82f6, #06b6d4); width: {{ $item['percentage'] }}%;"></div>
                        </div>
                    </div>
                @empty
                    <p style="font-size: 13px;" class="rp-text-muted">لا توجد بيانات منصات.</p>
                @endforelse
            </div>
        </div>

        <!-- Needs Breakdown Card -->
        <div class="rp-card" style="padding: 24px;">
            <h3 class="font-bold rp-text-title" style="font-size: 15px; margin-bottom: 16px; border-bottom: 1px solid rgba(148, 163, 184, 0.2); padding-bottom: 10px;">توزيع العملاء الجدد حسب الاحتياج</h3>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                @forelse($data['needs_breakdown'] as $item)
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;" class="rp-text-title">
                            <span>{{ $item['name'] }}</span>
                            <span class="rp-text-muted">{{ $item['count'] }} عميل ({{ $item['percentage'] }}%)</span>
                        </div>
                        <div style="width: 100%; height: 8px; border-radius: 6px; background: rgba(148, 163, 184, 0.15); overflow: hidden;">
                            <div style="height: 100%; border-radius: 6px; background: linear-gradient(90deg, #10b981, #14b8a6); width: {{ $item['percentage'] }}%;"></div>
                        </div>
                    </div>
                @empty
                    <p style="font-size: 13px;" class="rp-text-muted">لا توجد بيانات احتياجات.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>

