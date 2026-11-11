/** Ответ на вход: JWT и момент его истечения. */
export interface AuthToken {
  token: string;
  tokenType: 'Bearer';
  expiresAt: string;
}

/** Учётные данные с login-страницы. */
export interface Credentials {
  username: string;
  password: string;
}
