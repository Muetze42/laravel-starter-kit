export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  // config: ConfigInterface
  // currentUser: CurrentUserInterface | null
  // httpStatus: HttpStatusInterface
  // translations: { [key: string]: string }
}
