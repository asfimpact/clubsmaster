import admin from './admin'
import business from './business'
import dashboard from './dashboard'
import system from './system'

export default [
  {
    heading: 'Dashboard',
  },
  ...dashboard,
  ...admin,
  {
    heading: 'Business Management',
  },
  ...business,
  {
    heading: 'System Settings',
  },
  ...system,
]

