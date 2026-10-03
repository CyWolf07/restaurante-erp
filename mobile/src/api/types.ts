export interface User { id: string; name: string; role: string; role_label: string; capabilities: {read_orders: boolean; mark_ready: boolean} }
export interface Order { id: string; table_number: number; status: string; status_label: string; total: string | null; items: {id: string; name: string; quantity: number; comments: string | null; modifiers: {name: string; quantity: number}[]}[] }
export interface OrderPage { data: Order[]; meta: {current_page: number; last_page: number; total: number} }
