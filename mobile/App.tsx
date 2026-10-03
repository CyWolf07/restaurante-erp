import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Button, FlatList, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { ApiError, request } from './src/api/client';
import { session } from './src/auth/session';
import type { Order, OrderPage, User } from './src/api/types';

export default function App() {
  const [token, setToken] = useState<string>();
  const [user, setUser] = useState<User>();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [orders, setOrders] = useState<Order[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState('');
  const lock = useRef(false);
  const generation = useRef(0);

  async function clearSession() {
    generation.current++;
    setToken(undefined); setUser(undefined); setOrders([]); setPassword('');
    await session.clear();
  }
  async function report(cause: unknown) {
    if (cause instanceof ApiError && [401, 403].includes(cause.status)) await clearSession();
    setError(cause instanceof Error ? cause.message : 'Error inesperado.');
  }
  async function load(access: string, target = 1) {
    const current = generation.current;
    const result = await request<OrderPage>(`orders?page=${target}`, access);
    if (current !== generation.current) return;
    setOrders(result.data); setPage(result.meta.current_page); setLastPage(result.meta.last_page);
  }
  async function operation(action: () => Promise<void>) {
    if (lock.current) return;
    lock.current = true; setBusy(true); setError('');
    try { await action(); } catch (cause) { await report(cause); }
    finally { lock.current = false; setBusy(false); }
  }
  useEffect(() => {
    void operation(async () => {
      const saved = await session.read();
      if (!saved) return;
      const result = await request<{user: User}>('me', saved);
      setToken(saved); setUser(result.user); await load(saved);
    });
  }, []);
  function ready(order: Order) {
    Alert.alert('Confirmar pedido listo', `Mesa ${order.table_number}. ¿Terminó su preparación?`, [
      {text: 'Volver', style: 'cancel'},
      {text: 'Marcar listo', onPress: () => { void operation(async () => {
        await request(`orders/${order.id}/ready`, token, {}); await load(token!, page);
      }); }},
    ]);
  }
  return <SafeAreaProvider><SafeAreaView style={styles.root}><StatusBar style="dark" />
    <KeyboardAvoidingView style={styles.root} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={styles.header}><Text style={styles.title}>Restaurant ERP</Text>
        {user && <Text>{user.name} · {user.role_label}</Text>}
        <Text style={styles.notice}>Base móvil · cobros, cierres y facturación disponibles en PC.</Text>
        {!!error && <Text accessibilityRole="alert" style={styles.error}>{error}</Text>}
        {busy && <ActivityIndicator accessibilityLabel="Cargando" />}
      </View>
      {!user ? <ScrollView contentContainerStyle={styles.form} keyboardShouldPersistTaps="handled">
        <Text>Inicia sesión con correo y contraseña. El PIN local no se utiliza por internet.</Text>
        <TextInput accessibilityLabel="Correo" placeholder="Correo" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" style={styles.input} />
        <TextInput accessibilityLabel="Contraseña" placeholder="Contraseña" value={password} onChangeText={setPassword} secureTextEntry autoComplete="password" style={styles.input} />
        <Button title="Entrar" disabled={busy || !email || !password} onPress={() => { void operation(async () => {
          const result = await request<{token: string; user: User}>('auth/login', undefined, {email: email.trim(), password, device_name: `${Platform.OS} Restaurant ERP`});
          try { await session.save(result.token); } catch (cause) { await request('auth/logout', result.token, {}).catch(() => undefined); throw cause; }
          setToken(result.token); setUser(result.user); setPassword(''); await load(result.token);
        }); }} />
      </ScrollView> : <>
        <View style={styles.actions}><Button title="Actualizar" disabled={busy} onPress={() => { void operation(() => load(token!, page)); }} />
          <Button title="Salir" disabled={busy} onPress={() => { void operation(async () => {
            try { await request('auth/logout', token, {}); } finally { await clearSession(); }
          }); }} /></View>
        <FlatList data={orders} keyExtractor={item => item.id} contentContainerStyle={styles.list} refreshing={busy} onRefresh={() => { void operation(() => load(token!, 1)); }}
          ListEmptyComponent={<Text>{busy ? 'Consultando pedidos…' : 'No hay pedidos activos para tu rol.'}</Text>}
          renderItem={({item}) => <View style={styles.card}>
            <Text style={styles.title}>Mesa {item.table_number} · {item.status_label}</Text>
            {item.items.map(line => <View key={line.id}><Text>{line.quantity} × {line.name}</Text>
              {!!line.comments && <Text>Observaciones: {line.comments}</Text>}
              {line.modifiers.map((modifier, index) => <Text key={index}>+ {modifier.quantity} × {modifier.name}</Text>)}
            </View>)}
            {item.total !== null && <Text>Total: COP {item.total}</Text>}
            {user.capabilities.mark_ready && item.status === 'in_kitchen' && <Button title="Marcar listo" disabled={busy} onPress={() => ready(item)} />}
          </View>} />
        <View style={styles.actions}><Button title="Anterior" disabled={busy || page <= 1} onPress={() => { void operation(() => load(token!, page - 1)); }} /><Text>{page} / {lastPage}</Text>
          <Button title="Siguiente" disabled={busy || page >= lastPage} onPress={() => { void operation(() => load(token!, page + 1)); }} /></View>
      </>}
    </KeyboardAvoidingView>
  </SafeAreaView></SafeAreaProvider>;
}
const styles = StyleSheet.create({
  root: {flex: 1, backgroundColor: '#f4f6f8'}, header: {padding: 16, gap: 8}, title: {fontSize: 20, fontWeight: '600', flexShrink: 1},
  notice: {color: '#425466'}, error: {color: '#b42318'}, form: {padding: 16, gap: 16, width: '100%', maxWidth: 620, alignSelf: 'center'},
  input: {borderWidth: 1, borderColor: '#667085', borderRadius: 8, padding: 12, backgroundColor: 'white', fontSize: 16},
  actions: {padding: 12, flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 12},
  list: {padding: 16, gap: 12, width: '100%', maxWidth: 900, alignSelf: 'center'}, card: {backgroundColor: 'white', padding: 16, borderRadius: 12, gap: 10},
});
