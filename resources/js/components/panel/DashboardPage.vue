<template>
    <div>
        <!-- Stat Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3" v-for="stat in statsRow1" :key="stat.label">
                <div class="stat-card">
                    <div class="stat-card-icon" :class="stat.iconBg">
                        <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
                    </div>
                    <div class="stat-card-body">
                        <div class="stat-card-label">{{ stat.label }}</div>
                        <div class="stat-card-value">{{ stat.value }}</div>
                        <div class="stat-card-sub">
                            <span v-if="stat.trend" class="badge-trend" :class="stat.trend.dir">
                                <i class="bi" :class="stat.trend.dir === 'up' ? 'bi-arrow-up-short' : 'bi-arrow-down-short'"></i>
                                {{ stat.trend.pct }}
                            </span>
                            {{ stat.sub }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3" v-for="stat in statsRow2" :key="stat.label">
                <div class="stat-card">
                    <div class="stat-card-icon" :class="stat.iconBg">
                        <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
                    </div>
                    <div class="stat-card-body">
                        <div class="stat-card-label">{{ stat.label }}</div>
                        <div class="stat-card-value">{{ stat.value }}</div>
                        <div class="stat-card-sub">{{ stat.sub }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8">
                <earnings-bar-chart :earnings="weeklyEarnings" />
            </div>
            <div class="col-12 col-lg-4">
                <members-walkins-chart :members="membersCount" :walk-ins="walkInsCount" />
            </div>
        </div>

        <!-- Check-ins + Branches + Trainers -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <div>
                            <div class="panel-card-title">Check-ins / Attendance</div>
                            <div class="panel-card-sub">Today's entries across all branches</div>
                        </div>
                        <a href="#" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="panel-card-body p-0">
                        <table class="panel-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Branch</th>
                                    <th>Plan / Rate</th>
                                    <th>Time In</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="checkIns.length === 0" class="empty-row">
                                    <td colspan="5">
                                        <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                        <div class="mt-1 text-muted small">No check-ins recorded today.</div>
                                    </td>
                                </tr>
                                <tr v-for="row in checkIns" :key="row.id" v-else>
                                    <td>{{ row.name }}</td>
                                    <td>{{ row.type }}</td>
                                    <td>{{ row.branch }}</td>
                                    <td>{{ row.plan }}</td>
                                    <td>{{ row.timeIn }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4 d-flex flex-column gap-3">
                <div class="panel-card">
                    <div class="panel-card-header">
                        <div class="panel-card-title">Branches</div>
                        <a href="#" class="panel-card-action">Manage <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="panel-card-body">
                        <div class="branch-status-row" v-for="branch in branches" :key="branch.name">
                            <span class="branch-status-dot me-2" :class="branch.online ? 'online' : 'offline'"></span>
                            <span class="branch-status-name">{{ branch.name }}</span>
                            <span class="ms-auto branch-status-badge" :class="branch.online ? 'open' : 'soon'">
                                {{ branch.online ? "Open" : "Coming Soon" }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-card-header">
                        <div class="panel-card-title">Trainers</div>
                        <a href="#" class="panel-card-action">View <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="panel-card-body">
                        <div class="text-muted small text-center py-2">No trainers added yet.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Members + Recent Sales -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <div>
                            <div class="panel-card-title">Recent Members</div>
                            <div class="panel-card-sub">Latest registrations</div>
                        </div>
                        <a href="#" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="panel-card-body p-0">
                        <table class="panel-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Plan</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="recentMembers.length === 0" class="empty-row">
                                    <td colspan="4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                        <div class="mt-1 text-muted small">No members yet.</div>
                                    </td>
                                </tr>
                                <tr v-for="m in recentMembers" :key="m.id" v-else>
                                    <td>{{ m.name }}</td>
                                    <td>{{ m.plan }}</td>
                                    <td>{{ m.branch }}</td>
                                    <td>{{ m.status }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <div>
                            <div class="panel-card-title">Recent Sales</div>
                            <div class="panel-card-sub">Latest transactions</div>
                        </div>
                        <a href="#" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="panel-card-body p-0">
                        <table class="panel-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Item</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="recentSales.length === 0" class="empty-row">
                                    <td colspan="4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                        <div class="mt-1 text-muted small">No sales recorded yet.</div>
                                    </td>
                                </tr>
                                <tr v-for="s in recentSales" :key="s.id" v-else>
                                    <td>{{ s.name }}</td>
                                    <td>{{ s.item }}</td>
                                    <td>{{ s.amount }}</td>
                                    <td>{{ s.date }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring + Payroll -->
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <div>
                            <div class="panel-card-title">Expiring Soon</div>
                            <div class="panel-card-sub">Memberships expiring within 7 days</div>
                        </div>
                        <a href="#" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="panel-card-body p-0">
                        <table class="panel-table">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Branch</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="expiringSoon.length === 0" class="empty-row">
                                    <td colspan="4">
                                        <i class="bi bi-check-circle text-muted" style="font-size: 1.5rem"></i>
                                        <div class="mt-1 text-muted small">No memberships expiring soon.</div>
                                    </td>
                                </tr>
                                <tr v-for="e in expiringSoon" :key="e.id" v-else>
                                    <td>{{ e.member }}</td>
                                    <td>{{ e.plan }}</td>
                                    <td>{{ e.branch }}</td>
                                    <td>{{ e.expires }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <div>
                            <div class="panel-card-title">Employee Payouts</div>
                            <div class="panel-card-sub">Pending this pay period</div>
                        </div>
                        <a href="#" class="panel-card-action">Manage <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                    <div class="panel-card-body p-0">
                        <table class="panel-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Role</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="pendingPayroll.length === 0" class="empty-row">
                                    <td colspan="4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                        <div class="mt-1 text-muted small">No pending payouts.</div>
                                    </td>
                                </tr>
                                <tr v-for="p in pendingPayroll" :key="p.id" v-else>
                                    <td>{{ p.employee }}</td>
                                    <td>{{ p.role }}</td>
                                    <td>{{ p.amount }}</td>
                                    <td>{{ p.status }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import EarningsBarChart from "./charts/EarningsBarChart.vue";
import MembersWalkinsChart from "./charts/MembersWalkinsChart.vue";

export default {
    components: {
        EarningsBarChart,
        MembersWalkinsChart,
    },
    data: function () {
        return {
            statsRow1: [
                { label: "Total Members", value: "—", iconBg: "bg-primary-soft", icon: "bi-people-fill", iconColor: "text-primary", trend: { dir: "up", pct: "0%" }, sub: "this month" },
                { label: "Check-ins Today", value: "—", iconBg: "bg-success-soft", icon: "bi-person-check-fill", iconColor: "text-success", trend: { dir: "up", pct: "0%" }, sub: "vs yesterday" },
                { label: "Revenue Today", value: "₱—", iconBg: "bg-warning-soft", icon: "bi-receipt", iconColor: "text-warning", trend: { dir: "up", pct: "0%" }, sub: "vs yesterday" },
                { label: "Revenue This Month", value: "₱—", iconBg: "bg-danger-soft", icon: "bi-cash-stack", iconColor: "text-danger", trend: { dir: "up", pct: "0%" }, sub: "vs last month" },
            ],
            statsRow2: [
                { label: "Active Trainers", value: "—", iconBg: "bg-primary-soft", icon: "bi-person-badge-fill", iconColor: "text-primary", sub: "across all branches" },
                { label: "Employees", value: "—", iconBg: "bg-success-soft", icon: "bi-person-workspace", iconColor: "text-success", sub: "active staff" },
                { label: "Walk-ins Today", value: "—", iconBg: "bg-warning-soft", icon: "bi-person-fill-exclamation", iconColor: "text-warning", sub: "non-member walk-ins" },
                { label: "Pending Payroll", value: "₱—", iconBg: "bg-danger-soft", icon: "bi-wallet2", iconColor: "text-danger", sub: "unpaid this period" },
            ],
            weeklyEarnings: [3200, 4750, 2900, 5100, 6300, 8200, 4400],
            membersCount: 256,
            walkInsCount: 150,
            branches: [
                { name: "Calabanga", online: true },
                { name: "Naga", online: false },
                { name: "Legazpi", online: false },
            ],
            checkIns: [],
            recentMembers: [],
            recentSales: [],
            expiringSoon: [],
            pendingPayroll: [],
        };
    },
    mounted() {},
    methods: {},
};
</script>
