import { abilitiesPlugin } from '@casl/vue'
import { ability } from './ability'

export default function (app) {
  const userAbilityRules = useCookie('userAbilityRules')
  
  if (userAbilityRules.value)
    ability.update(userAbilityRules.value)

  app.use(abilitiesPlugin, ability, {
    useGlobalProperties: true,
  })
}
