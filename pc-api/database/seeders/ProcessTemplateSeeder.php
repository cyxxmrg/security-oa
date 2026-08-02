<?php

namespace Database\Seeders;

use App\Models\ProcessTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 深化施工 V1.1 - 工序模板 Seeder
 *
 * 5 大行业 × 20 工序 = 100 条模板
 * 行业: security(安防) / building(楼宇) / transport(交通) / energy(能源) / industrial(工业)
 *
 * 字段: industry, code, name, description, standard_duration_days,
 *       required_qualifications, safety_requirements,
 *       quality_checkpoints, acceptance_criteria
 */
class ProcessTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // 通用安全要求(每行业可覆盖)
        $safetyCommon = '持证上岗(高空作业证/电工证);作业前安全交底;佩戴安全帽/绝缘鞋/防护手套;严禁带电作业;高空作业必须系安全带';

        // 通用质量检查点
        $checkpointsCommon = [
            ['id' => 'cp1', 'name' => '材料合格证/检测报告齐全', 'required' => true],
            ['id' => 'cp2', 'name' => '工艺符合规范要求', 'required' => true],
            ['id' => 'cp3', 'name' => '接线牢固/标识清晰', 'required' => true],
            ['id' => 'cp4', 'name' => '功能测试正常', 'required' => true],
            ['id' => 'cp5', 'name' => '现场清理/成品保护', 'required' => false],
        ];

        // 通用验收标准
        $criteriaCommon = [
            ['id' => 'ac1', 'name' => '符合设计图纸要求'],
            ['id' => 'ac2', 'name' => '符合国家/行业标准(GB 50303/GB 50168 等)'],
            ['id' => 'ac3', 'name' => '监理/甲方签字确认'],
        ];

        $templates = [];

        // ============ security 安防监控 ============
        $securityCommon = [
            '施工前确认设备点位/管线走向与图纸一致',
            '线缆敷设避免急弯/挤压,弯曲半径 ≥ 6 倍线径',
            '室外设备做好防水/防雷接地(接地电阻 ≤ 4Ω)',
        ];
        $securityCriteria = array_merge($criteriaCommon, [
            ['id' => 'ac4', 'name' => 'GB 50348-2018 安全防范工程技术标准'],
            ['id' => 'ac5', 'name' => 'GB 50198-2011 民用闭路监视电视系统工程技术规范'],
        ]);
        $securityQuals = ['电工证', '高处作业证'];
        $security = [
            ['SP001', '现场勘察', 1, 8,  '现场环境勘察,确认设备点位/管线走向/电源位置'],
            ['SP002', '方案设计', 2, 16, '出具深化设计图纸,含点位图/管线图/系统图'],
            ['SP003', '材料采购', 3, 4,  '设备/线材/辅材按清单采购到位,做进场检验'],
            ['SP004', '线管敷设', 5, 80, 'PVC/镀锌管按图施工,管卡间距 ≤ 1.5m'],
            ['SP005', '线缆敷设', 5, 60, '电源线/信号线/光纤分别穿管,标识清晰'],
            ['SP006', '设备开箱检验', 1, 4, '核对型号/数量/附件,做开箱记录'],
            ['SP007', '支架/立杆安装', 3, 40, '基础浇筑养护 ≥ 7 天,法兰盘水平度 ≤ 1mm/m'],
            ['SP008', '摄像机/球机安装', 3, 24, '固定牢靠,角度符合设计,镜头避免强光直射'],
            ['SP009', '交换机/NVR 安装', 1, 8,  '19"标准机柜内安装,留足散热空间'],
            ['SP010', '电源/光缆熔接', 3, 24, '光缆熔接损耗 ≤ 0.05dB/点,电源线径符合设计'],
            ['SP011', '接地施工', 1, 16, '接地极埋深 ≥ 0.8m,接地电阻 ≤ 4Ω'],
            ['SP012', '防雷施工', 1, 16, '电源/信号浪涌保护器安装到位'],
            ['SP013', '设备加电调试', 2, 16, '单机通电测试,确认无异常'],
            ['SP014', '平台对接', 3, 24, 'NVR/平台对接,完成设备注册与协议对接'],
            ['SP015', '图像质量调优', 2, 16, '清晰度/亮度/对比度/码流调至最佳'],
            ['SP016', '录像存储配置', 1, 8,  '存储周期 ≥ 30 天,循环覆盖正常'],
            ['SP017', '报警联动测试', 1, 8,  '移动侦测/遮挡/丢失/报警联动正常'],
            ['SP018', '系统试运行', 3, 8,  '72 小时连续运行无故障'],
            ['SP019', '甲方初验', 2, 8,  '出具初验报告,问题清单整改'],
            ['SP020', '培训交付', 2, 16, '用户培训,文档资料移交,系统正式上线'],
        ];
        foreach ($security as [$code, $name, $days, $hours, $desc]) {
            $templates[] = $this->build(
                ProcessTemplate::INDUSTRY_SECURITY, '视频监控', $code, $name, $desc,
                $days, $hours, $securityQuals, $safetyCommon,
                $securityCommon, $securityCriteria
            );
        }

        // ============ building 楼宇自控 ============
        $buildingQuals = ['电工证', '建(构)筑物消防员'];
        $buildingCommon = [
            'DDC控制器电源独立,UPS 供电 ≥ 30 分钟',
            '传感器/执行器安装位置避开振动/热源/强电磁干扰',
            '通讯线缆采用屏蔽线,屏蔽层单端接地',
        ];
        $buildingCriteria = array_merge($criteriaCommon, [
            ['id' => 'ac4', 'name' => 'GB 50339-2013 智能建筑工程质量验收规范'],
            ['id' => 'ac5', 'name' => 'GB/T 50314-2015 智能建筑设计标准'],
        ]);
        $building = [
            ['BL001', '现场勘察', 1, 8,  '楼宇设备/管线/控制点勘察'],
            ['BL002', '楼宇自控方案设计', 2, 16, '点位表/系统图/控制策略'],
            ['BL003', 'DDC 控制器选型', 1, 4,  'I/O 点数留 20% 余量'],
            ['BL004', '线管敷设', 5, 80, '金属线管接地连续'],
            ['BL005', '线缆敷设', 5, 60, '屏蔽线单独穿管'],
            ['BL006', '传感器安装', 3, 24, '温湿度/CO2/压力传感器位置准确'],
            ['BL007', '执行器安装', 3, 24, '风阀/水阀执行器与阀体匹配'],
            ['BL008', '阀门安装', 3, 24, '方向正确,便于检修'],
            ['BL009', 'DDC 控制器安装', 2, 16, '控制箱垂直度 ≤ 1.5mm/m'],
            ['BL010', '通讯网络搭建', 2, 16, 'BACnet/Modbus 总线终端电阻匹配'],
            ['BL011', '软件组态编程', 5, 40, '控制逻辑/联动策略/报警阈值'],
            ['BL012', '监控点配置', 3, 24, '点位命名规范,显示正确'],
            ['BL013', '联动逻辑调试', 3, 24, '空调/照明/给排水联动'],
            ['BL014', '空调系统联调', 3, 24, '温度/湿度/压差控制稳定'],
            ['BL015', '照明系统联调', 2, 16, '定时/场景/感应控制'],
            ['BL016', '给排水系统联调', 2, 16, '液位/压力/泵阀控制'],
            ['BL017', '能源计量联调', 2, 16, '电/水/气/热计量数据上传'],
            ['BL018', '系统集成测试', 3, 24, 'BA 系统与消防/安防联动'],
            ['BL019', '系统试运行', 7, 8,  '168 小时连续运行'],
            ['BL020', '培训交付', 3, 16, '运维/管理培训,资料移交'],
        ];
        foreach ($building as [$code, $name, $days, $hours, $desc]) {
            $templates[] = $this->build(
                ProcessTemplate::INDUSTRY_BUILDING, '楼宇自控', $code, $name, $desc,
                $days, $hours, $buildingQuals, $safetyCommon,
                $buildingCommon, $buildingCriteria
            );
        }

        // ============ transport 智能交通 ============
        $transportQuals = ['电工证', '高处作业证', '施工员证'];
        $transportCommon = [
            '杆件基础开挖避开地下管线,人工探坑',
            '高空作业设置作业平台,严禁抛掷工具',
            '路口施工需报交警审批,设置警示标志',
        ];
        $transportCriteria = array_merge($criteriaCommon, [
            ['id' => 'ac4', 'name' => 'GB 14886-2016 道路交通信号灯设置与安装规范'],
            ['id' => 'ac5', 'name' => 'GA/T 496-2014 闯红灯自动记录系统通用技术条件'],
        ]);
        $transport = [
            ['TR001', '现场勘察', 1, 8,  '路口/路段交通流量勘察'],
            ['TR002', '交通组织方案', 2, 16, '施工期间交通组织/导改方案'],
            ['TR003', '杆件基础施工', 5, 80, '开挖/钢筋/浇筑/养护'],
            ['TR004', '杆件安装', 2, 40, '立杆垂直度 ≤ 5mm/m'],
            ['TR005', '信号灯安装', 2, 16, '方位/角度/亮度符合国标'],
            ['TR006', '电子警察安装', 2, 24, '摄像机+补光灯+线圈/雷达'],
            ['TR007', '测速设备安装', 2, 16, '测速点位置符合规范'],
            ['TR008', '卡口设备安装', 2, 16, '车牌识别+车辆捕获'],
            ['TR009', '诱导屏安装', 3, 24, '立柱+屏体+配电'],
            ['TR010', '中心设备安装', 2, 16, '服务器/存储/交换机机柜'],
            ['TR011', '网络通讯', 3, 24, '光纤/交换机/VLAN 划分'],
            ['TR012', '电源施工', 2, 16, '供电电缆+配电箱'],
            ['TR013', '接地防雷', 1, 8,  '接地电阻 ≤ 4Ω'],
            ['TR014', '信号配时调试', 2, 16, '相位/配时/绿波带'],
            ['TR015', '闯红灯抓拍调试', 1, 8,  '违章捕获率 ≥ 95%'],
            ['TR016', '车牌识别调试', 1, 8,  '识别准确率 ≥ 95%'],
            ['TR017', '平台对接', 2, 16, '设备接入/数据上传'],
            ['TR018', '系统联调', 3, 24, '前端+中心+平台端到端'],
            ['TR019', '试运行', 7, 8,  '7×24 小时运行'],
            ['TR020', '验收交付', 3, 16, '交警/甲方验收,资料移交'],
        ];
        foreach ($transport as [$code, $name, $days, $hours, $desc]) {
            $templates[] = $this->build(
                ProcessTemplate::INDUSTRY_TRANSPORT, '交通信号', $code, $name, $desc,
                $days, $hours, $transportQuals, $safetyCommon,
                $transportCommon, $transportCriteria
            );
        }

        // ============ energy 能源电力 ============
        $energyQuals = ['高压电工证', '低压电工证', '高处作业证'];
        $energyCommon = [
            '高压作业必须两人在场,一人操作一人监护',
            '停电作业必须验电/放电/装设接地线/悬挂标识牌',
            '使用绝缘工具,穿戴绝缘防护用品',
        ];
        $energyCriteria = array_merge($criteriaCommon, [
            ['id' => 'ac4', 'name' => 'GB 50150-2016 电气装置安装工程电气设备交接试验标准'],
            ['id' => 'ac5', 'name' => 'GB 50053-2013 20kV 及以下变电所设计规范'],
        ]);
        $energy = [
            ['EN001', '现场勘察', 1, 8,  '变配电室/线路勘察'],
            ['EN002', '电气设计', 3, 24, '一次/二次系统图'],
            ['EN003', '设备选型', 1, 8,  '高压柜/变压器/低压柜'],
            ['EN004', '高压柜安装', 3, 24, '基础槽钢/柜体就位/母线连接'],
            ['EN005', '变压器安装', 2, 24, '就位/接线/接地'],
            ['EN006', '低压柜安装', 2, 24, '柜体安装/母线连接'],
            ['EN007', '母线施工', 2, 24, '母线槽/密集母线敷设'],
            ['EN008', '电缆敷设', 5, 80, '高压电缆/低压电缆/控制电缆'],
            ['EN009', '电缆头制作', 2, 24, '热缩/冷缩电缆头制作'],
            ['EN010', '二次接线', 5, 60, '电流/电压/控制/信号回路'],
            ['EN011', '接地施工', 2, 24, '接地网/接地极/等电位'],
            ['EN012', '直流系统安装', 1, 8,  '充电模块/蓄电池组'],
            ['EN013', '保护装置调试', 3, 24, '过流/速断/差动/距离保护'],
            ['EN014', '自动化装置调试', 3, 24, '测控/通讯/远动装置'],
            ['EN015', '高压试验', 2, 24, '绝缘/耐压/直流电阻/油样试验'],
            ['EN016', '送电试运行', 1, 16, '分步送电/空载运行'],
            ['EN017', '负荷测试', 1, 16, '满负荷运行/温升测试'],
            ['EN018', '系统联调', 2, 24, '保护/自动化/通讯联调'],
            ['EN019', '试运行', 7, 8,  '168 小时连续运行'],
            ['EN020', '验收交付', 3, 16, '电力部门/甲方验收'],
        ];
        foreach ($energy as [$code, $name, $days, $hours, $desc]) {
            $templates[] = $this->build(
                ProcessTemplate::INDUSTRY_ENERGY, '变配电', $code, $name, $desc,
                $days, $hours, $energyQuals, $safetyCommon,
                $energyCommon, $energyCriteria
            );
        }

        // ============ industrial 工业自动化 ============
        $industrialQuals = ['电工证', '自动化工程师'];
        $industrialCommon = [
            'PLC 控制柜内接线整齐,强弱电分离',
            '接地系统单点接地,接地电阻 ≤ 1Ω',
            '工艺管线试压合格后再连接仪表',
        ];
        $industrialCriteria = array_merge($criteriaCommon, [
            ['id' => 'ac4', 'name' => 'GB/T 3766-2015 液压系统通用技术条件'],
            ['id' => 'ac5', 'name' => 'GB/T 20438-2017 过程工业领域安全仪表系统'],
        ]);
        $industrial = [
            ['IN001', '现场勘察', 1, 8,  '工艺流程/设备/管线勘察'],
            ['IN002', '工艺方案设计', 3, 24, '控制策略/IO 清单/网络架构'],
            ['IN003', 'PLC 选型', 1, 8,  'I/O 点数+20% 余量'],
            ['IN004', '仪表选型', 1, 8,  '压力/流量/温度/液位'],
            ['IN005', '桥架安装', 5, 80, '梯级桥架/托盘式桥架'],
            ['IN006', '线管敷设', 5, 80, '金属线管/防爆区域穿管'],
            ['IN007', '电缆敷设', 5, 80, '控制电缆/信号电缆/动力电缆'],
            ['IN008', 'PLC 柜制作', 5, 40, '柜体/元件布置/接线'],
            ['IN009', '操作台安装', 2, 16, '工控机/按钮/指示灯'],
            ['IN010', '仪表安装', 3, 24, '取压点/接线/密封'],
            ['IN011', '阀门安装', 3, 24, '调节阀/切断阀/手阀'],
            ['IN012', '接线调试', 5, 60, '动力/控制/信号回路'],
            ['IN013', 'PLC 编程', 7, 60, '梯形图/结构化文本/SFC'],
            ['IN014', 'HMI 组态', 5, 40, '画面/报警/历史/权限'],
            ['IN015', 'SCADA 对接', 5, 40, 'OPC/Modbus/Profinet'],
            ['IN016', 'PID 参数整定', 5, 40, '比例/积分/微分参数'],
            ['IN017', '系统联调', 7, 60, '单机/子系统/全系统'],
            ['IN018', '工艺试车', 7, 24, '空载/负载/满负荷试车'],
            ['IN019', '性能测试', 3, 24, '响应时间/控制精度/稳定性'],
            ['IN020', '验收交付', 3, 16, '工艺/设备/文档验收'],
        ];
        foreach ($industrial as [$code, $name, $days, $hours, $desc]) {
            $templates[] = $this->build(
                ProcessTemplate::INDUSTRY_INDUSTRIAL, 'PLC 控制', $code, $name, $desc,
                $days, $hours, $industrialQuals, $safetyCommon,
                $industrialCommon, $industrialCriteria
            );
        }

        // 批量插入(去重:基于 industry+code 唯一索引)
        DB::transaction(function () use ($templates) {
            foreach ($templates as $t) {
                ProcessTemplate::updateOrCreate(
                    ['industry' => $t['industry'], 'code' => $t['code']],
                    $t
                );
            }
        });

        $this->command->info("工序模板 Seeder 完成: " . count($templates) . " 条 (5行业 × 20工序)");
    }

    /** 构建单条模板数据 */
    private function build(
        string $industry, string $category, string $code, string $name, string $desc,
        int $days, float $hours, array $quals, string $safety, array $commonNotes, array $criteria
    ): array {
        $checkpoints = [
            ['id' => 'cp1', 'name' => '材料/设备合格证齐全', 'required' => true],
            ['id' => 'cp2', 'name' => '工艺/技术规范符合设计', 'required' => true],
            ['id' => 'cp3', 'name' => '接线/连接牢固可靠', 'required' => true],
            ['id' => 'cp4', 'name' => '标识/标牌清晰完整', 'required' => false],
            ['id' => 'cp5', 'name' => '功能/性能测试合格', 'required' => true],
        ];
        $checkpoints = array_merge($checkpoints, array_map(function ($n) {
            return ['id' => 'cp_' . substr(md5($n), 0, 6), 'name' => $n, 'required' => false];
        }, $commonNotes));

        return [
            'industry'                  => $industry,
            'category'                  => $category,
            'code'                      => $code,
            'name'                      => $name,
            'description'               => $desc,
            'standard_duration_days'    => $days,
            'standard_man_hours'        => $hours,
            'required_qualifications'   => $quals,
            'safety_requirements'       => $safety,
            'quality_checkpoints'       => $checkpoints,
            'acceptance_criteria'       => $criteria,
            'sort_order'                => (int)substr($code, 2),
            'is_active'                 => true,
            'created_by'                => null,
        ];
    }
}
