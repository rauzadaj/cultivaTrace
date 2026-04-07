import { beforeEach, describe, expect, it, vi } from 'vitest'

let requestInterceptor: ((config: Record<string, unknown>) => Record<string, unknown>) | undefined
let responseErrorInterceptor: ((error: unknown) => Promise<unknown>) | undefined

const requestMock = vi.fn()
const refreshPostMock = vi.fn()

const primaryHttp = {
  interceptors: {
    request: {
      use: vi.fn((handler: typeof requestInterceptor) => {
        requestInterceptor = handler
      }),
    },
    response: {
      use: vi.fn((_: unknown, errorHandler: typeof responseErrorInterceptor) => {
        responseErrorInterceptor = errorHandler
      }),
    },
  },
  request: requestMock,
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
}

const refreshHttp = {
  interceptors: {
    request: { use: vi.fn() },
    response: { use: vi.fn() },
  },
  post: refreshPostMock,
}

vi.mock('axios', () => {
  const create = vi.fn()
    .mockReturnValueOnce(primaryHttp)
    .mockReturnValueOnce(refreshHttp)

  return {
    default: { create },
    create,
  }
})

describe('api auth refresh interceptor', () => {
  beforeEach(() => {
    vi.resetModules()
    localStorage.clear()
    requestMock.mockReset()
    refreshPostMock.mockReset()
    requestInterceptor = undefined
    responseErrorInterceptor = undefined
  })

  it('refreshes the access token and retries the original request on 401', async () => {
    localStorage.setItem('cultivatrace_token', 'expired-access-token')
    localStorage.setItem('cultivatrace_refresh_token', 'valid-refresh-token')

    await import('./api')

    expect(responseErrorInterceptor).toBeTypeOf('function')

    refreshPostMock.mockResolvedValue({
      data: {
        token: 'new-access-token',
        refreshToken: 'new-refresh-token',
        expiresIn: 900,
      },
    })
    requestMock.mockResolvedValue({
      data: { ok: true },
      status: 200,
    })

    const result = await responseErrorInterceptor?.({
      response: { status: 401 },
      config: {
        url: '/plants',
        headers: {},
      },
    })

    expect(refreshPostMock).toHaveBeenCalledWith('/auth/token/refresh', {
      refreshToken: 'valid-refresh-token',
    })
    expect(localStorage.getItem('cultivatrace_token')).toBe('new-access-token')
    expect(localStorage.getItem('cultivatrace_refresh_token')).toBe('new-refresh-token')
    expect(requestMock).toHaveBeenCalledWith(expect.objectContaining({
      _retry: true,
      headers: expect.objectContaining({
        Authorization: 'Bearer new-access-token',
      }),
    }))
    expect(result).toEqual({
      data: { ok: true },
      status: 200,
    })
  })
})
