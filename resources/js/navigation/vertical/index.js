import business from './business'
import dashboard from './dashboard'
import system from './system'

export default [
  {
    heading: 'Dashboard',
  },
  ...dashboard,
  {
    heading: 'Business Management',
  },
  ...business,
  {
    heading: 'System Settings',
  },
  ...system,
]
