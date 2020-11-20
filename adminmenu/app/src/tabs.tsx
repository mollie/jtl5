import React from 'react'
import { TabInfo } from '@webstollen/react-jtl-plugin/lib'
import Dashboard from "./tabs/Dashboard";

type Tabs = Array<TabInfo>

const tabs: Tabs = [{
    title: 'Dashboard',
    isDashboard: true,
    component: Dashboard
}]

export default tabs
