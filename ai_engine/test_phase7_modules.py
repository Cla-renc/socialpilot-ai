import asyncio
from ai_engine.brand_visual_intelligence import BrandVisualIntelligenceService
from ai_engine.media_provider_manager import MediaProviderManager
from ai_engine.media_processing import MediaProcessingService
from ai_engine.video_storyboard import VideoStoryboardService
from ai_engine.image_generation import ImageGenerationService
from ai_engine.video_generation import VideoGenerationService
from ai_engine.main import app

def main():
    # 1. Brand visual intelligence
    bvis = BrandVisualIntelligenceService()
    p1 = bvis.build_visual_profile({'name': 'IronFit', 'category': 'fitness & discipline', 'industry': 'sports'})
    assert p1['visual_identity'] == 'dark_cinematic', f"Expected dark_cinematic, got {p1['visual_identity']}"

    p2 = bvis.build_visual_profile({'name': 'ShopMart', 'category': 'retail', 'industry': 'product store'})
    assert p2['visual_identity'] in ('product_ad', 'commercial')

    p3 = bvis.build_visual_profile({'name': 'LearnFast', 'category': 'education', 'industry': 'training'})
    assert p3['visual_identity'] == 'educational'

    p4 = bvis.build_visual_profile({'name': 'SaaSFlow', 'category': 'tech', 'industry': 'saas b2b'})
    assert p4['visual_identity'] == 'corporate'

    p5 = bvis.build_visual_profile({'name': 'DeepThought', 'category': 'wisdom', 'industry': 'philosophy'})
    assert p5['visual_identity'] == 'inspirational'

    # Overridden by visual_settings
    p_custom = bvis.build_visual_profile(
        {'name': 'IronFit', 'category': 'fitness'},
        visual_settings={'visual_identity': 'minimalist', 'primary_color': '#FF0000'}
    )
    assert p_custom['visual_identity'] == 'minimalist'
    assert p_custom['primary_color'] == '#FF0000'

    prompt = bvis.build_image_prompt(p1, 'Post workout energy')
    assert 'IronFit' in prompt
    neg = bvis.build_negative_prompt(p1)
    assert 'watermark' in neg

    # 2. Media provider manager
    mpm = MediaProviderManager()
    img_prov = mpm.check_image_provider('internal')
    assert img_prov['available'] is True
    assert img_prov['status'] == 'ready_no_generation'

    all_prov = mpm.list_all_providers()
    assert len(all_prov['image_providers']) >= 4
    assert len(all_prov['video_providers']) >= 3

    # 3. Media processing
    fmt = MediaProcessingService.get_format('instagram_feed')
    assert fmt['aspect_ratio'] == '1:1'
    size_openai = MediaProcessingService.get_size_for_provider('16:9', 'openai')
    assert size_openai == '1792x1024'
    recs = MediaProcessingService.get_recommended_formats('instagram')
    assert len(recs) >= 3

    # 4. Video storyboard
    sb = VideoStoryboardService.generate_storyboard(
        brand_payload={'name': 'IronFit'},
        visual_profile=p1,
        draft={'title': 'Never Quit'},
        video_idea=None,
        platform='instagram_reel',
        aspect_ratio='9:16',
        num_scenes=6
    )
    assert len(sb['scenes']) == 6
    assert sb['title'] == 'Never Quit'
    assert 'background_music_suggestion' in sb

    # 5. Image & Video generation
    async def test_gen():
        img_res = await ImageGenerationService.generate_image(
            brand_payload={'name': 'IronFit'},
            visual_settings=None,
            objective='poster',
        )
        assert img_res['status'] == 'configuration_required'
        assert 'OPENAI_API_KEY' in img_res['message']

        vid_res = await VideoGenerationService.generate_video(
            brand_payload={'name': 'IronFit'},
            visual_profile=p1,
            draft=None,
            video_idea='Epic promo',
            objective='brand_video',
            platform='instagram',
            aspect_ratio='9:16',
        )
        assert vid_res['status'] == 'configuration_required'
        assert 'RUNWAY_API_KEY' in vid_res['message']

    asyncio.run(test_gen())
    print("ALL MODULE CHECKS PASSED SUCCESSFULLY!")

if __name__ == '__main__':
    main()
