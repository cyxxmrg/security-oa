// 车辆模块共享类型 — 由各组件统一引用, 收窄类型、消除隐式宽松类型

export interface VehicleOption {
  id?: number
  plate_no?: string
  brand?: string
  model?: string
  [key: string]: unknown
}

export interface FuelCardVehicle {
  plate_no?: string
  brand?: string
  model?: string
  [key: string]: unknown
}

export interface FuelCard {
  id?: number
  card_no?: string
  card_name?: string
  vehicle_id?: number | null
  vehicle?: FuelCardVehicle | null
  balance?: number
  issue_date?: string | null
  expire_date?: string | null
  status?: string
  notes?: string
  [key: string]: unknown
}

export interface FuelCardRechargeCard {
  card_no?: string
  card_name?: string
  [key: string]: unknown
}

export interface FuelCardRecharge {
  id?: number
  card?: FuelCardRechargeCard | null
  amount?: number
  payment_method?: string
  operator?: string
  voucher_no?: string
  notes?: string
  recharge_date?: string
  [key: string]: unknown
}

export interface InsuranceRecord {
  id?: number
  vehicle_id?: number | null
  vehicle?: FuelCardVehicle | null
  type?: 'compulsory' | 'commercial'
  insurance_company?: string
  policy_no?: string
  premium?: number
  start_date?: string | null
  end_date?: string | null
  status?: string
  notes?: string
  [key: string]: unknown
}

export interface MaintenanceHandler { name?: string; [key: string]: unknown }

export interface MaintenanceRecord {
  id?: number
  vehicle_id?: number | null
  vehicle?: FuelCardVehicle | null
  maintenance_type?: 'routine' | 'repair' | 'inspection'
  maintenance_date?: string | null
  mileage?: number | null
  cost?: number
  description?: string
  next_maintenance_date?: string | null
  next_maintenance_mileage?: number | null
  handledByUser?: MaintenanceHandler | null
  handled_by?: string
  [key: string]: unknown
}
