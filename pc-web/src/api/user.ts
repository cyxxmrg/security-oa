import { post } from '@/utils/request'

// 登录（stores/user.ts 也定义了 login，但部分场景可能直接调用此函数）
export function login(data: { username: string; password: string }) {
  return post('/auth/login', data)
}
