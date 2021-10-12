import { TabInfo } from '@webstollen/react-jtl-plugin/lib'
import Orders from './tabs/Orders'
import Dashboard from './tabs/Dashboard'
import Queue from './tabs/Queue'

type Tabs = Array<TabInfo>

const tabs: Tabs = [
  {
    title: 'Dashboard',
    isDashboard: true,
    component: Dashboard,
  },
  {
    title: 'Bestellungen',
    isDashboard: false,
    component: Orders,
  },
  {
    title: 'Warteschlange',
    isDashboard: false,
    component: Queue,
  },
]

export default tabs
