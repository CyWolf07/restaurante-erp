import * as SecureStore from 'expo-secure-store';
export const session = {
  read: () => SecureStore.getItemAsync('restaurant.access-token'),
  save: (token: string) => SecureStore.setItemAsync('restaurant.access-token', token),
  clear: () => SecureStore.deleteItemAsync('restaurant.access-token'),
};
