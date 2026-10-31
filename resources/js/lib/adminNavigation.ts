export const adminGroups = [
    {
        key: 'pa_clients',
        items: [
            { href: '/admin/organizations', key: 'pa_organizations', icon: 'pi-building' },
            { href: '/admin/users', key: 'pa_users', icon: 'pi-users' },
        ],
    },
    {
        key: 'pa_growth',
        items: [
            { href: '/admin/leads', key: 'pa_leads', icon: 'pi-inbox' },
            { href: '/admin/campaigns', key: 'pa_campaigns', icon: 'pi-chart-line' },
        ],
    },
    {
        key: 'pa_catalog',
        items: [
            { href: '/admin/cuisines', key: 'pa_cuisines', icon: 'pi-th-large' },
            { href: '/admin/ingredients', key: 'pa_ingredients', icon: 'pi-list' },
        ],
    },
    {
        key: 'pa_monitoring',
        items: [
            { href: '/admin/operations', key: 'pa_operations', icon: 'pi-wave-pulse' },
            { href: '/admin/communication-logs', key: 'pa_logs', icon: 'pi-arrows-h' },
            { href: '/admin/emails', key: 'pa_emails', icon: 'pi-send' },
            { href: '/admin/email-alerts', key: 'pa_alerts', icon: 'pi-bell' },
        ],
    },
    { key: 'pa_configuration', items: [{ href: '/admin/menu-ai', key: 'pa_ai', icon: 'pi-sparkles' }] },
];
export const adminActive = (url: string, href: string) => {
    const path = url.split('?')[0];
    return href === '/admin' ? path === href : path === href || path.startsWith(href + '/');
};
