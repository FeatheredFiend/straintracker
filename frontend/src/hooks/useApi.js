import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client.js'

/**
 * GET a URL on mount (and whenever it changes); `reload` refetches and
 * `setData` lets callers patch the cached result after a mutation.
 */
export function useApi(url) {
  const [state, setState] = useState({ data: null, error: null, loading: true })

  const load = useCallback(async () => {
    setState((s) => ({ ...s, loading: true, error: null }))
    try {
      setState({ data: await api.get(url), error: null, loading: false })
    } catch (error) {
      setState({ data: null, error, loading: false })
    }
  }, [url])

  useEffect(() => {
    load()
  }, [load])

  const setData = useCallback((update) => {
    setState((s) => ({ ...s, data: typeof update === 'function' ? update(s.data) : update }))
  }, [])

  return { ...state, reload: load, setData }
}
