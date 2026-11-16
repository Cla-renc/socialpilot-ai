import 'package:flutter/material.dart';

import '../../services/publishing_service.dart';

class PublishNowScreen extends StatefulWidget {
  const PublishNowScreen({super.key, this.service});

  final PublishingService? service;

  @override
  State<PublishNowScreen> createState() => _PublishNowScreenState();
}

class _PublishNowScreenState extends State<PublishNowScreen> {
  late final PublishingService _service;
  final TextEditingController _contentController = TextEditingController();
  final TextEditingController _socialAccountIdController =
      TextEditingController();
  final TextEditingController _brandIdController = TextEditingController();
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? PublishingService();
  }

  @override
  void dispose() {
    _contentController.dispose();
    _socialAccountIdController.dispose();
    _brandIdController.dispose();
    super.dispose();
  }

  Future<void> _publish() async {
    final content = _contentController.text.trim();
    final socialAccountIdStr = _socialAccountIdController.text.trim();
    final brandIdStr = _brandIdController.text.trim();

    if (content.isEmpty) {
      _showSnackBar('Content is required.');
      return;
    }
    final socialAccountId = int.tryParse(socialAccountIdStr);
    if (socialAccountId == null) {
      _showSnackBar('Social Account ID must be a valid integer.');
      return;
    }
    final brandId = int.tryParse(brandIdStr);
    if (brandId == null) {
      _showSnackBar('Brand ID must be a valid integer.');
      return;
    }

    setState(() => _loading = true);
    try {
      final attempt = await _service.publishNow(
        socialAccountId: socialAccountId,
        content: content,
        brandId: brandId,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
                'Published! Status: ${attempt.status} (ID: ${attempt.id})'),
          ),
        );
        _contentController.clear();
        _socialAccountIdController.clear();
        _brandIdController.clear();
      }
    } catch (e) {
      if (mounted) {
        _showSnackBar('Publish failed: $e');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showSnackBar(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Publish Now')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Publish Now',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 20),
                TextField(
                  controller: _contentController,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'Content',
                    hintText: 'Write your post content here...',
                    alignLabelWithHint: true,
                    prefixIcon: Padding(
                      padding: EdgeInsets.only(bottom: 60),
                      child: Icon(Icons.edit_outlined),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _socialAccountIdController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Social Account ID',
                    prefixIcon: Icon(Icons.account_circle_outlined),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _brandIdController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Brand ID',
                    prefixIcon: Icon(Icons.business_outlined),
                  ),
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _loading ? null : _publish,
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 18),
                  ),
                  child: _loading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Publish'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
