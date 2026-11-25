import { usePage } from '@inertiajs/vue3'

export function usePermissions() {
    const page = usePage()

    const can = (permission) => page.props.can?.includes(permission) ?? false

    return { can }
}
