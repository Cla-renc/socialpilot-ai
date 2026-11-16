import 'package:flutter/material.dart';

import '../../services/analytics_service.dart';

class AnalyticsReportsScreen extends StatefulWidget {
  const AnalyticsReportsScreen({super.key});

  @override
  State<AnalyticsReportsScreen> createState() => _AnalyticsReportsScreenState();
}

class _AnalyticsReportsScreenState extends State<AnalyticsReportsScreen> {
  final AnalyticsService _service = AnalyticsService();
  List<dynamic> _reports = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _service.getReports();
      final items = data['data'] ?? data['items'] ?? [];
      setState(() {
        _reports = items is List ? items : [];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Color _statusColor(String? status) {
    switch (status) {
      case 'completed':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'failed':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Analytics Reports'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : _reports.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: const [
                            Icon(Icons.description_outlined,
                                size: 56, color: Colors.grey),
                            SizedBox(height: 16),
                            Text(
                              'No reports generated yet.\nUse the web Analytics Studio to generate PDF or CSV reports.',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: Colors.grey),
                            ),
                          ],
                        ),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _reports.length,
                        itemBuilder: (_, i) {
                          final report =
                              _reports[i] as Map<String, dynamic>;
                          final status = report['status']?.toString();
                          final format =
                              report['format']?.toString().toUpperCase();
                          return Card(
                            margin: const EdgeInsets.only(bottom: 10),
                            child: ListTile(
                              leading: Icon(
                                format == 'PDF'
                                    ? Icons.picture_as_pdf_outlined
                                    : Icons.table_chart_outlined,
                                color: Theme.of(context).colorScheme.primary,
                              ),
                              title: Text(
                                report['report_name']?.toString() ??
                                    report['report_type']?.toString() ??
                                    'Report',
                              ),
                              subtitle: Text(
                                '${report['report_type'] ?? ''} · ${report['period_start'] ?? ''} → ${report['period_end'] ?? ''}',
                                style: const TextStyle(fontSize: 12),
                              ),
                              trailing: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8, vertical: 4),
                                decoration: BoxDecoration(
                                  color: _statusColor(status),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  status ?? 'unknown',
                                  style: const TextStyle(
                                      color: Colors.white, fontSize: 11),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
