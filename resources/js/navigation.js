import {
    Activity,
    BarChart3,
    BookOpen,
    Database,
    Download,
    Gauge,
    LayoutDashboard,
    ScrollText,
    Shield,
    Table2,
    Upload,
} from '@lucide/vue';

export const navigationItems = [
    { name: '首页概览', path: '/', icon: Gauge },
    { name: '数据源', path: '/data-sources', icon: Database },
    { name: '数据集', path: '/datasets', icon: Table2 },
    { name: '语义层', path: '/semantic-layer', icon: BookOpen },
    { name: '图表配置', path: '/charts', icon: BarChart3 },
    { name: '仪表盘', path: '/dashboards', icon: LayoutDashboard },
    { name: '导入任务', path: '/imports', icon: Upload },
    { name: '导出任务', path: '/exports', icon: Download },
    { name: '查询日志', path: '/query-logs', icon: ScrollText },
    { name: '权限管理', path: '/permissions', icon: Shield },
    { name: '系统监控', path: '/monitor', icon: Activity },
];
