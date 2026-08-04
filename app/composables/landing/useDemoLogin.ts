// Connexion au compte de démonstration (bouton « Essayer la démo » du hero et du bandeau CTA).
// L'API monte un compte éphémère pré-rempli et nous connecte directement.
export function useDemoLogin() {
  const { t } = useI18n()
  const auth = useAuthStore()
  const toast = useToast()
  const entering = ref(false)

  async function enterDemo(): Promise<void> {
    if (entering.value) return
    entering.value = true
    try {
      await auth.enterDemo()
      await navigateTo('/today')
    }
    catch {
      toast.add({ title: t('landing.demo.error'), color: 'error' })
    }
    finally {
      entering.value = false
    }
  }

  return { entering, enterDemo }
}
