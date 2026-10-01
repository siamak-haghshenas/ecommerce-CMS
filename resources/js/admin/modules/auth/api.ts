import { http } from '@/shared/http/client';
import type { OtpChallenge, Resource } from '@/shared/types/api';
import type { StaffUser } from './types';

async function data<T>(request: Promise<{ data: Resource<T> }>): Promise<T> {
    return (await request).data.data;
}

export const adminAuthApi = {
    loginWithPassword: (phoneNumber: string, password: string) =>
        data<StaffUser>(http.post('/admin/auth/login', { phone_number: phoneNumber, password })),

    sendLoginCode: (phoneNumber: string) =>
        data<OtpChallenge>(http.post('/admin/auth/login/otp', { phone_number: phoneNumber })),

    loginWithCode: (phoneNumber: string, code: string) =>
        data<StaffUser>(http.post('/admin/auth/login/otp/verify', { phone_number: phoneNumber, code })),

    me: () => data<StaffUser>(http.get('/admin/auth/me')),

    logout: () => http.post('/admin/auth/logout'),
};
