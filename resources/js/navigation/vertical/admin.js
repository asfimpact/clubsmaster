export default [
  {
    heading: 'BUSINESS MANAGEMENT',
    action: 'manage',
    subject: 'Admin',
  },
  {
    title: 'Members',
    icon: { icon: 'tabler-users' },
    to: { name: 'admin-members' },
    action: 'manage',
    subject: 'Admin',
  },
  {
    heading: 'SYSTEM CONFIGURATION',
    action: 'manage',
    subject: 'Admin',
  },
  {
    title: 'Pricing Plans',
    icon: { icon: 'tabler-settings-automation' },
    to: { name: 'admin-pricing-mgmt' },
    action: 'manage',
    subject: 'Admin',
  },
  {
    title: 'Security Settings',
    icon: { icon: 'tabler-shield-lock' },
    to: { name: 'admin-security-mgmt' },
    action: 'manage',
    subject: 'Admin',
  },
  {
    title: 'Email Server',
    icon: { icon: 'tabler-mail-cog' },
    to: { name: 'admin-email-settings' },
    action: 'manage',
    subject: 'Admin',
  },
]
