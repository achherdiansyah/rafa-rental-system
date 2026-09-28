import { describe, expect, it, vi } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useLatestCall } from './useLatestCall'

describe('useLatestCall', () => {
  it('ignores stale failures and keeps the latest success', async () => {
    const { result } = renderHook(() => useLatestCall())

    const slowFail = new Promise((_resolve, reject) => setTimeout(() => reject(new Error('old request died')), 30))
    const fastOk = Promise.resolve('ok')

    const first = act(async () => result.current.run(() => slowFail))
    const second = act(async () => result.current.run(() => fastOk))

    expect(await second).toBe('ok')
    await first
    // stale rejection must not throw to the caller
  })

  it('rethrows the latest real failure', async () => {
    const { result } = renderHook(() => useLatestCall())

    await act(async () => {
      await expect(result.current.run(() => Promise.reject(new Error('server 500')))).rejects.toThrow('server 500')
    })
  })

  it('returns null for stale successes', async () => {
    const { result } = renderHook(() => useLatestCall())

    let resolveOld!: (v: string) => void
    const oldCall = new Promise<string>((resolve) => {
      resolveOld = resolve
    })

    const first = act(async () => result.current.run(() => oldCall))
    const second = act(async () => result.current.run(() => Promise.resolve('new')))

    await second
    resolveOld('late')
    expect(await first).toBeNull()
  })

  it('silences in-flight failure when a newer request already succeeded', async () => {
    const { result } = renderHook(() => useLatestCall())

    let rejectOld!: (e: Error) => void
    const oldCall = new Promise<never>((_resolve, reject) => {
      rejectOld = reject
    })

    const first = act(async () => result.current.run(() => oldCall))
    const second = act(async () => result.current.run(() => Promise.resolve('ok')))

    await second
    rejectOld(new Error('late failure'))
    await first // must NOT rethrow — this is the reported false-error bug
    expect(vi.fn()).not.toHaveBeenCalled()
  })
})