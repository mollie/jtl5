import React from 'react'
import Dashboard from "./js/Tabs/Dashboard";
import PaymentMethods from './js/Tabs/PaymentMethods';
import Orders from './js/Tabs/Orders';

export interface Tab {
    title: string
    isDashboard?: boolean
    component?: React.FC
}

export default [
    {
        title: 'Dashbord',
        isDashboard: true,
        component: Dashboard,
    } as Tab,
    {
        title: 'Bestellungen',
        isDashboard: false,
        component: Orders
    } as Tab,
    {
        title: 'Zahlungsarten',
        isDashboard: false,
        component: PaymentMethods
    } as Tab
];