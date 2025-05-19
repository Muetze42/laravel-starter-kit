/* eslint-disable */
// noinspection JSUnusedGlobalSymbols

import { PageProps as InertiaPageProps } from '@inertiajs/core'
import { AxiosInstance } from 'axios'
import { PageProps as AppPageProps } from './'

declare global {
  interface Window {
    axios: AxiosInstance
  }
}

let axios: AxiosInstance

declare module 'vue' {
  interface ComponentCustomProperties {}
}

declare module '@inertiajs/core' {
  interface PageProps extends InertiaPageProps, AppPageProps {}
}
