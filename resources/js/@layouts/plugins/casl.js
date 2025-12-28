import { useAbility } from '@casl/vue'
import { getCurrentInstance } from 'vue'

/**
 * Returns ability result if ACL is configured or else just return true
 * We should allow passing string | undefined to can because for admin ability we omit defining action & subject
 *
 * Useful if you don't know if ACL is configured or not
 * Used in @core files to handle absence of ACL without errors
 *
 * @param {string} action CASL Actions // https://casl.js.org/v4/en/guide/intro#basics
 * @param {string} subject CASL Subject // https://casl.js.org/v4/en/guide/intro#basics
 */
export const can = (action, subject) => {
  const vm = getCurrentInstance()
  if (!vm)
    return false

  // 👉 If both action and subject are missing, it's a shared item
  if (!action && !subject)
    return true

  const localCan = vm.proxy && '$can' in vm.proxy
    
  return localCan ? vm.proxy?.$can(action, subject) : true
}

/**
 * Check if user can view item based on it's ability
 * Based on item's action and subject & Hide group if all of it's children are hidden
 * @param {object} item navigation object item
 */
export const canViewNavMenuGroup = item => {
  const hasAnyVisibleChild = item.children.some(i => can(i.action, i.subject))

  // If subject and action is defined in item => Return based on children visibility (Hide group if no child is visible)
  // Else check for ability using provided subject and action along with checking if has any visible child
  if (!(item.action && item.subject))
    return hasAnyVisibleChild
  
  return can(item.action, item.subject) && hasAnyVisibleChild
}

export const canNavigate = to => {
  const ability = useAbility()

  // Check if any route in the matched chain has specific permissions
  const hasDefinedPermissions = to.matched.some(route => route.meta.action && route.meta.subject)

  // If no permissions are defined anywhere in the route chain, allow access to logged-in users
  if (!hasDefinedPermissions)
    return true

  // Otherwise, ensure the user has the required ability for at least one matched route
  return to.matched.some(route => {
    if (route.meta.action && route.meta.subject)
      return ability.can(route.meta.action, route.meta.subject)
    
    return false
  })
}
