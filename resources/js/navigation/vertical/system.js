export default [
  {
    title: 'Account Settings',
    icon: { icon: 'tabler-user-cog' },
    to: { name: 'pages-account-settings-tab', params: { tab: 'account' } },
  },
  {
    title: 'FAQ',
    icon: { icon: 'tabler-help' },
    to: 'pages-faq',
  },
  {
    title: 'Support',
    icon: { icon: 'tabler-headphones' },
    to: 'front-pages-help-center', // Map to help center or support
  },
  {
    title: 'Documentation',
    href: 'https://demos.pixinvent.com/vuexy-vuejs-admin-template/documentation/',
    icon: { icon: 'tabler-file-text' },
    target: '_blank',
  },
]
